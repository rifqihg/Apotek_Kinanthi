<?php

namespace App\Http\Controllers\Control\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Control\WarehouseInventory;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\Category;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\AuditTrail;
use App\Models\Control\StockMutation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KasirSaleController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->query('start_date', today()->format('Y-m-d'));
        $endDate = $request->query('end_date', today()->format('Y-m-d'));
        $search = $request->query('search');

        $prescriptionsQuery = Prescription::with(['patient', 'prescriptionItems'])
            ->where('status', 'siap_ambil');

        if ($search) {
            $prescriptionsQuery->where(function ($q) use ($search) {
                $q->where('prescription_number', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($qp) use ($search) {
                      $qp->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $prescriptions = $prescriptionsQuery->orderBy('updated_at', 'asc')
            ->paginate(15)
            ->withQueryString();

        $sales = Sale::with(['prescription:id,patient_id', 'prescription.patient:id,name'])
            ->where('kasir_id', auth()->id())
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->latest()
            ->limit(100)
            ->get(['id', 'kasir_id', 'prescription_id', 'sale_number', 'total_amount', 'discount_amount', 'paid_amount', 'change_amount', 'payment_method', 'status', 'created_at']);

        return Inertia::render('Control/Kasir/Queue', [
            'prescriptions' => $prescriptions,
            'sales' => $sales,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $search,
            ]
        ]);
    }

    public function show($prescriptionId)
    {
        $prescription = Prescription::with([
            'patient',
            'prescriptionItems.medicine',
            'prescriptionItems.compoundedProduct'
        ])->findOrFail($prescriptionId);

        if ($prescription->status === 'selesai') {
            return redirect()->route('control.kasir.queue')->with('error', 'Resep ini sudah selesai diproses.');
        }

        if ($prescription->status !== 'siap_ambil') {
            return redirect()->route('control.kasir.queue')->with('error', 'Resep belum siap untuk diproses pembayaran.');
        }

        return Inertia::render('Control/Kasir/Checkout', compact('prescription'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'prescription_id' => 'required|exists:prescriptions,id',
            'payment_method' => 'required|in:tunai,transfer,qris',
            'discount_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.prescription_item_id' => 'required|exists:prescription_items,id',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        if (Sale::where('prescription_id', $v['prescription_id'])->where('status', 'paid')->exists()) {
            return back()->with('error', 'Resep ini sudah pernah dibayar. Tidak dapat diproses ulang.');
        }

        try {
            return DB::transaction(function () use ($v) {
                $prescription = Prescription::with(['patient'])->findOrFail($v['prescription_id']);
                $resolvedItems = [];

                foreach ($v['items'] as $itemData) {
                    $pItem = PrescriptionItem::with(['medicine', 'compoundedProduct'])->findOrFail($itemData['prescription_item_id']);
                    $qty = (float) $itemData['quantity'];

                    if ($pItem->item_type === 'obat_jadi') {
                        $med = $pItem->medicine ?: Medicine::findOrFail($pItem->medicine_id);
                        $unitPrice = (float) $med->selling_price;
                        $qtyInBase = $qty;

                        if ($pItem->unit === $med->satuan_besar && (float) $med->selling_price_large > 0) {
                            $unitPrice = (float) $med->selling_price_large;
                            $qtyInBase = $qty * max(1, (float) $med->rasio_konversi);
                            if ($med->satuan_menengah) {
                                $qtyInBase *= max(1, (float) $med->conversion_factor);
                            }
                        } elseif ($pItem->unit === $med->satuan_menengah && (float) $med->selling_price_mid > 0) {
                            $unitPrice = (float) $med->selling_price_mid;
                            $qtyInBase = $qty * max(1, (float) $med->conversion_factor);
                        } elseif ($pItem->unit === $med->satuan_besar && !$med->satuan_menengah) {
                            $qtyInBase = $qty * max(1, (float) $med->rasio_konversi);
                        }

                        $resolvedItems[] = [
                            'prescription_item' => $pItem,
                            'prescription_item_id' => $pItem->id,
                            'item_type' => 'obat_jadi',
                            'medicine_id' => $med->id,
                            'name' => $med->nama_obat ?? 'Unknown Obat',
                            'unit' => $pItem->unit,
                            'quantity' => $qty,
                            'qty_in_base' => $qtyInBase,
                            'unit_price' => $unitPrice,
                        ];
                    } else {
                        $cp = $pItem->compoundedProduct ?: ($pItem->compounded_product_id ? CompoundedProduct::find($pItem->compounded_product_id) : null);
                        if (!$cp) abort(422, 'Item racikan tidak ditemukan.');
                        $this->validateCompoundedItem($cp, $qty);

                        $resolvedItems[] = [
                            'prescription_item' => $pItem,
                            'prescription_item_id' => $pItem->id,
                            'item_type' => 'racikan',
                            'compounded_product_id' => $cp->id,
                            'model' => $cp,
                            'name' => $cp->nama_produk ?? ($pItem->custom_formula_name ?? 'Racikan'),
                            'unit' => $pItem->unit,
                            'quantity' => $qty,
                            'unit_price' => (float) $cp->harga_jual,
                        ];
                    }
                }

                $sale = $this->executeSaleTransaction($v, $resolvedItems, $prescription);
                return redirect()->route('control.kasir.sales.receipt', $sale->id)->with('success', 'Transaksi berhasil disimpan.');
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->with('error', $e->getMessage());
        }
    }

    public function receipt($saleId)
    {
        $sale = Sale::with(['kasir', 'prescription.patient', 'saleItems.medicine', 'saleItems.compoundedProduct'])->findOrFail($saleId);

        abort_if($sale->kasir_id !== auth()->id() && auth()->user()->role !== 'admin', 403, 'Akses ditolak: Anda hanya dapat mencetak struk transaksi milik sendiri.');

        return Inertia::render('Control/Kasir/Receipt', [
            'sale' => $sale,
        ]);
    }

    public function otcIndex(Request $request)
    {
        $kasirId = auth()->id();
        $search = $request->query('search');
        $category = $request->query('category');

        $medicines = Medicine::select(['id', 'category_id', 'nama_obat', 'kode_obat', 'satuan_stok', 'base_unit', 'selling_price', 'stok_total', 'foto_obat'])
            ->with([
                'category:id,name',
                'latestIncomingStock:incoming_stocks.id,incoming_stocks.medicine_id,incoming_stocks.harga_jual_akhir',
                'warehouseInventories' => fn($q) => $q->select(['id', 'medicine_id', 'stock_on_hand'])
                    ->where('location_type', 'kasir')
                    ->where('location_id', $kasirId)
                    ->where('stock_on_hand', '>', 0)
                    ->where('status', 'active')
                    ->where('is_frozen', false)
                    ->whereDate('expired_date', '>', now())
            ])
            ->whereIn('id', function ($sub) use ($kasirId) {
                $sub->select('medicine_id')
                    ->from('warehouse_inventories')
                    ->where('location_type', 'kasir')
                    ->where('location_id', $kasirId)
                    ->where('stock_on_hand', '>', 0)
                    ->where('status', 'active')
                    ->where('is_frozen', false)
                    ->whereDate('expired_date', '>', now());
            })
            ->when($search, fn($q) => $q->where(fn($sub) => $sub->where('nama_obat', 'like', "%{$search}%")->orWhere('kode_obat', 'like', "%{$search}%")))
            ->when($category && $category !== 'all', function ($q) use ($category) {
                if (strtolower($category) === 'lainnya') {
                    $q->where(function ($sub) {
                        $sub->whereHas('category', fn($cq) => $cq->where('name', 'Lainnya'))
                            ->orWhereNull('category_id');
                    });
                } else {
                    $q->whereHas('category', fn($cq) => $cq->where('name', $category));
                }
            })
            ->orderBy('nama_obat')
            ->limit(40)
            ->get()
            ->map(fn($med) => [
                'id' => $med->id,
                'category_id' => $med->category_id,
                'category' => $med->category ? ['id' => $med->category->id, 'nama_kategori' => $med->category->name] : null,
                'nama_obat' => $med->nama_obat,
                'kode_obat' => $med->kode_obat,
                'satuan' => $med->satuan_stok ?: ($med->base_unit ?: 'Pcs'),
                'selling_price' => (float) ($med->selling_price > 0 ? $med->selling_price : ($med->latestIncomingStock?->harga_jual_akhir ?? 0)),
                'stok_kasir' => (float) $med->warehouseInventories->sum('stock_on_hand'),
                'foto_obat' => $med->foto_obat,
            ]);

        $compoundedProducts = CompoundedProduct::select(['id', 'nama_produk', 'batch_no', 'harga_jual', 'qty_hasil', 'status', 'pricing_status'])
            ->where('status', 'active')
            ->where('pricing_status', 'priced')
            ->whereNotNull('harga_jual')
            ->when($search, fn($q) => $q->where(fn($sub) => $sub->where('nama_produk', 'like', "%{$search}%")->orWhere('batch_no', 'like', "%{$search}%")))
            ->orderBy('nama_produk')
            ->limit(20)
            ->get();

        return inertia('Control/Kasir/OTC', [
            'medicines' => $medicines,
            'compoundedProducts' => $compoundedProducts,
            'categories' => Category::select(['id', 'name as nama_kategori', 'name'])->orderBy('name')->get(),
            'filters' => ['search' => $search ?? '', 'category' => $category ?? 'all'],
        ]);
    }

    public function otcStore(Request $request)
    {
        $v = $request->validate([
            'payment_method' => 'required|in:tunai,transfer,qris',
            'discount_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:obat_jadi,racikan',
            'items.*.id' => 'required|numeric',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        try {
            return DB::transaction(function () use ($v) {
                $resolvedItems = [];

                foreach ($v['items'] as $itemData) {
                    $qty = (float) $itemData['quantity'];
                    if ($itemData['item_type'] === 'obat_jadi') {
                        $med = Medicine::findOrFail($itemData['id']);
                        $resolvedItems[] = [
                            'item_type' => 'obat_jadi',
                            'medicine_id' => $med->id,
                            'name' => $med->nama_obat,
                            'unit' => $med->satuan_stok ?: ($med->base_unit ?: 'Pcs'),
                            'quantity' => $qty,
                            'qty_in_base' => $qty,
                            'unit_price' => (float) $med->selling_price,
                        ];
                    } else {
                        $cp = CompoundedProduct::findOrFail($itemData['id']);
                        $this->validateCompoundedItem($cp, $qty);
                        $resolvedItems[] = [
                            'item_type' => 'racikan',
                            'compounded_product_id' => $cp->id,
                            'model' => $cp,
                            'name' => $cp->nama_produk,
                            'unit' => $cp->satuan ?: 'Paket',
                            'quantity' => $qty,
                            'unit_price' => (float) $cp->harga_jual,
                        ];
                    }
                }

                $sale = $this->executeSaleTransaction($v, $resolvedItems);
                return redirect()->route('control.kasir.sales.receipt', $sale->id)->with('success', 'Transaksi OTC berhasil disimpan.');
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->with('error', $e->getMessage());
        }
    }

    /**
     * Eksekusi penyimpanan transaksi penjualan, pemotongan stok, dan pencatatan audit trail atomik.
     */
    private function executeSaleTransaction(array $v, array $resolvedItems, ?Prescription $prescription = null): Sale
    {
        $calc = $this->calculateTotals($resolvedItems, $v['discount_amount'] ?? 0, (float) $v['paid_amount']);
        $userId = (int) auth()->id();
        $isResep = $prescription !== null;

        $sale = Sale::create([
            'kasir_id' => $userId,
            'prescription_id' => $prescription?->id,
            'sale_type' => $isResep ? 'resep' : 'otc',
            'total_amount' => $calc['finalTotal'],
            'discount_amount' => $calc['discount'],
            'paid_amount' => $v['paid_amount'],
            'change_amount' => $calc['change'],
            'payment_method' => $v['payment_method'],
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $refLabel = $isResep ? "resep {$prescription->prescription_number}" : "transaksi {$sale->sale_number}";

        foreach ($resolvedItems as $rItem) {
            $qty = $rItem['quantity'];
            $unitPrice = $rItem['unit_price'];

            SaleItem::create([
                'sale_id' => $sale->id,
                'prescription_item_id' => $rItem['prescription_item_id'] ?? null,
                'item_type' => $rItem['item_type'],
                'medicine_id' => $rItem['medicine_id'] ?? null,
                'compounded_product_id' => $rItem['compounded_product_id'] ?? null,
                'item_name' => $rItem['name'],
                'quantity' => $qty,
                'unit' => $rItem['unit'],
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice * $qty,
            ]);

            if ($rItem['item_type'] === 'obat_jadi') {
                $this->deductKasirStock((int) $rItem['medicine_id'], $rItem['qty_in_base'] ?? $qty, $sale, $userId, $sale->sale_type);
            } else {
                $cp = $rItem['model'];
                $cp->decrement('qty_hasil', $qty);

                StockMutation::create([
                    'item_id' => $cp->id,
                    'item_type' => CompoundedProduct::class,
                    'type' => 'keluar',
                    'quantity' => $qty,
                    'balance_after' => $cp->qty_hasil,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'note' => "Penjualan racikan {$cp->nama_produk} via {$refLabel}",
                ]);

                AuditTrail::log(
                    $userId,
                    $isResep ? 'compounding.stock_deducted' : 'compounding.stock_deducted_otc',
                    "Stok racikan {$cp->nama_produk} berkurang {$qty} via {$refLabel}",
                    CompoundedProduct::class,
                    $cp->id
                );
            }

            if (!empty($rItem['prescription_item'])) {
                $rItem['prescription_item']->update(['status' => 'selesai']);
            }
        }

        if ($prescription) {
            $prescription->update(['status' => 'selesai']);
        }

        $saleTypeUpper = $isResep ? '' : 'OTC ';
        AuditTrail::log($userId, 'sale.created', "Transaksi {$saleTypeUpper}{$sale->sale_number} senilai {$sale->total_amount} oleh kasir", Sale::class, $sale->id);

        return $sale;
    }

    /**
     * Validasi status harga dan ketersediaan stok produk racikan.
     */
    private function validateCompoundedItem(CompoundedProduct $product, float $qty): void
    {
        if ($product->pricing_status === 'pending_price' || (float) $product->harga_jual <= 0) {
            abort(422, 'Item racikan belum divalidasi harganya oleh Admin.');
        }
        if ($product->qty_hasil < $qty) {
            abort(422, "Stok produk racikan '{$product->nama_produk}' tidak mencukupi (sisa: {$product->qty_hasil}).");
        }
    }

    /**
     * Hitung subtotal, diskon, final total, dan kembalian transaksi.
     */
    private function calculateTotals(array $items, float $discountAmount, float $paidAmount): array
    {
        $subtotal = collect($items)->sum(fn($item) => $item['unit_price'] * $item['quantity']);
        $discount = max(0, $discountAmount);

        if ($discount > $subtotal) {
            throw new \Exception('Diskon tidak boleh lebih besar dari total belanja.');
        }

        $finalTotal = $subtotal - $discount;
        if ($paidAmount < $finalTotal) {
            throw new \Exception('Jumlah yang dibayarkan kurang dari total tagihan.');
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'finalTotal' => $finalTotal,
            'change' => $paidAmount - $finalTotal,
        ];
    }

    /**
     * Potong stok obat jadi kasir sesuai urutan FEFO dan sinkronkan ke katalog faktur.
     */
    private function deductKasirStock(int $medicineId, float $qtyNeeded, Sale $sale, int $userId, string $context): void
    {
        $inventories = WarehouseInventory::with('incomingStock')
            ->where('medicine_id', $medicineId)
            ->kasir($userId)
            ->where('stock_on_hand', '>', 0)
            ->where('status', 'active')
            ->where('is_frozen', false)
            ->whereDate('expired_date', '>', now())
            ->orderBy('expired_date', 'asc')
            ->lockForUpdate()
            ->get();

        $remaining = $qtyNeeded;
        $auditLogs = [];
        $now = now();
        $ip = request()->ip();

        foreach ($inventories as $inv) {
            if ($remaining <= 0) break;

            $qtyToDeduct = min($inv->stock_on_hand, $remaining);
            $inv->setMutationContext('sale', $sale, "Penjualan Faktur {$sale->sale_number}");
            $inv->stock_on_hand -= $qtyToDeduct;
            $inv->save();

            \App\Models\Control\StockLog::create([
                'medicine_id' => $medicineId,
                'action' => 'PENJUALAN_' . strtoupper($context),
                'qty_before' => $inv->stock_on_hand + $qtyToDeduct,
                'qty_after' => $inv->stock_on_hand,
                'change_amount' => -$qtyToDeduct,
                'reason' => "Penjualan Faktur {$sale->sale_number}",
                'user_id' => $userId,
            ]);

            if ($inv->incomingStock) {
                $incoming = $inv->incomingStock;
                $incoming->qty_converted = max(0, $incoming->qty_converted - $qtyToDeduct);
                $incoming->saveQuietly();
            }

            $auditLogs[] = [
                'user_id'        => $userId,
                'action'         => "stock.deducted_by_sale_{$context}",
                'description'    => "Stok obat dikurangi {$qtyToDeduct} via transaksi {$sale->id}",
                'auditable_type' => WarehouseInventory::class,
                'auditable_id'   => $inv->id,
                'old_values'     => null,
                'new_values'     => null,
                'ip_address'     => $ip,
                'performed_at'   => $now,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            $remaining -= $qtyToDeduct;
        }

        if (!empty($auditLogs)) {
            AuditTrail::insert($auditLogs);
        }

        if ($remaining > 0) {
            $medName = Medicine::find($medicineId)?->nama_obat ?? "ID {$medicineId}";
            abort(422, "Stok aktif/tidak kedaluwarsa tidak mencukupi untuk obat: {$medName}");
        }
        
        Medicine::find($medicineId)?->recalculateStock();
    }

    /**
     * Membatalkan transaksi kasir (Void) dengan otorisasi Admin, mengembalikan stok fisik dan racikan.
     */
    public function voidSale(Request $request, $saleId)
    {
        $v = $request->validate([
            'admin_username' => 'required|string',
            'admin_password' => 'required|string',
            'reason'         => 'nullable|string|max:500',
        ]);

        $adminUser = User::where('role', 'admin')
            ->where(function ($q) use ($v) {
                $q->where('email', $v['admin_username'])
                  ->orWhere('name', $v['admin_username']);
            })
            ->first();

        if (!$adminUser || !Hash::check($v['admin_password'], $adminUser->password)) {
            return back()->withErrors(['admin_password' => 'Otorisasi gagal: Kredensial Admin tidak valid.'])->with('error', 'Otorisasi gagal: Email/Nama atau password Admin salah.');
        }

        try {
            DB::transaction(function () use ($saleId, $adminUser, $v) {
                $sale = Sale::with(['saleItems'])->lockForUpdate()->findOrFail($saleId);

                if (!in_array($sale->status, ['paid', 'completed'])) {
                    throw new \Exception("Transaksi tidak dapat dibatalkan karena berstatus {$sale->status}.");
                }

                $kasirId = (int) auth()->id();

                foreach ($sale->saleItems as $item) {
                    if ($item->item_type === 'obat_jadi' && $item->medicine_id) {
                        $inv = WarehouseInventory::with('incomingStock')
                            ->where('medicine_id', $item->medicine_id)
                            ->kasir($sale->kasir_id)
                            ->where('status', 'active')
                            ->orderBy('expired_date', 'asc')
                            ->lockForUpdate()
                            ->first();

                        if (!$inv) {
                            $inv = WarehouseInventory::where('medicine_id', $item->medicine_id)
                                ->kasir($sale->kasir_id)
                                ->lockForUpdate()
                                ->first();
                        }

                        if ($inv) {
                            $inv->stock_on_hand += $item->quantity;
                            $inv->setMutationContext('sale_void', $sale, "Void Transaksi {$sale->sale_number} (Admin: {$adminUser->name})");
                            $inv->save();

                            if ($inv->incomingStock) {
                                $incoming = $inv->incomingStock;
                                $incoming->qty_converted += $item->quantity;
                                $incoming->saveQuietly();
                            }
                        } else {
                            $fallbackInv = WarehouseInventory::where('medicine_id', $item->medicine_id)
                                ->where('status', 'active')
                                ->lockForUpdate()
                                ->first();
                            if ($fallbackInv) {
                                $newKasirInv = WarehouseInventory::create([
                                    'incoming_stock_id' => $fallbackInv->incoming_stock_id,
                                    'medicine_id'       => $item->medicine_id,
                                    'batch_no'          => $fallbackInv->batch_no,
                                    'expired_date'      => $fallbackInv->expired_date,
                                    'stock_on_hand'     => $item->quantity,
                                    'status'            => 'active',
                                    'is_frozen'         => false,
                                    'location_type'     => 'kasir',
                                    'location_id'       => $sale->kasir_id,
                                ]);
                                $newKasirInv->setMutationContext('sale_void', $sale, "Void Transaksi {$sale->sale_number} (Admin: {$adminUser->name})");
                                $newKasirInv->save();
                            }
                        }
                    } elseif ($item->item_type === 'racikan' && $item->compounded_product_id) {
                        $cp = CompoundedProduct::lockForUpdate()->find($item->compounded_product_id);
                        if ($cp) {
                            // Ponytail & Standar Farmasi: Obat racikan kustom pasien yang batal tebus
                            // TIDAK BOLEH dikembalikan ke stok aktif (qty_hasil tidak di-increment).
                            // Item dicatat sebagai write-off/pemusnahan demi keselamatan pasien.
                            StockMutation::create([
                                'item_id'        => $cp->id,
                                'item_type'      => CompoundedProduct::class,
                                'type'           => 'Pemusnahan / Write-off',
                                'quantity'       => $item->quantity,
                                'balance_after'  => $cp->qty_hasil,
                                'reference_type' => Sale::class,
                                'reference_id'   => $sale->id,
                                'note'           => "Batal Tebus / Dimusnahkan - Void Transaksi {$sale->sale_number} (Admin: {$adminUser->name})",
                            ]);

                            AuditTrail::log(
                                $kasirId,
                                'compounding.disposal',
                                "Obat racikan {$cp->nama_produk} ({$item->quantity} {$item->unit}) berstatus Batal Tebus / Dimusnahkan akibat void transaksi {$sale->sale_number} (Admin: {$adminUser->name}).",
                                CompoundedProduct::class,
                                $cp->id
                            );
                        }
                    }
                }

                if ($sale->prescription_id) {
                    $prescription = Prescription::with('prescriptionItems')->find($sale->prescription_id);
                    if ($prescription) {
                        $hasRacikan = $sale->saleItems->contains(fn($it) => $it->item_type === 'racikan');
                        if ($hasRacikan) {
                            // Racikan kustom pasien yang batal tidak dapat direstock/digunakan lagi (telah dimusnahkan)
                            $prescription->update([
                                'status' => 'batal',
                                'notes'  => trim(($prescription->notes ?? '') . " [Batal via Void Kasir {$sale->sale_number}: Obat racikan dimusnahkan/write-off]"),
                            ]);
                        } else {
                            // Obat jadi telah dikembalikan ke stok kasir, resep dapat dikembalikan ke antrean 'siap_ambil'
                            $prescription->update(['status' => 'siap_ambil']);
                        }
                    }
                }

                $sale->update(['status' => 'cancelled']);

                $reasonText = !empty($v['reason']) ? " - Alasan: {$v['reason']}" : '';
                AuditTrail::log(
                    $kasirId,
                    'sale.void',
                    "Void Transaksi {$sale->sale_number} senilai " . number_format($sale->total_amount, 0, ',', '.') . " disetujui oleh Admin {$adminUser->name} (ID: {$adminUser->id}){$reasonText}",
                    Sale::class,
                    $sale->id
                );
            });

            return redirect()->back()->with('success', 'Transaksi berhasil dibatalkan (Void). Stok obat jadi dikembalikan, dan obat racikan dicatat sebagai pemusnahan/write-off.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->with('error', $e->getMessage());
        }
    }
}
