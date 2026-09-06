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
use App\Models\Control\CompoundedProduct;
use App\Models\Control\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KasirSaleController extends Controller
{
    public function index(Request $request)
    {
        $selectedDate = $request->query('date', today()->format('Y-m-d'));

        return Inertia::render('Control/Kasir/Queue', [
            'prescriptions' => Prescription::with(['patient', 'prescriptionItems'])->where('status', 'siap_ambil')->orderBy('updated_at', 'asc')->get(),
            'sales' => Sale::with(['prescription.patient'])->where('kasir_id', auth()->id())->whereDate('created_at', $selectedDate)->latest()->get(),
            'filters' => ['date' => $selectedDate]
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
            'items' => 'required|array',
            'items.*.prescription_item_id' => 'required|exists:prescription_items,id',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        if (Sale::where('prescription_id', $v['prescription_id'])->where('status', 'paid')->exists()) {
            return back()->with('error', 'Resep ini sudah pernah dibayar. Tidak dapat diproses ulang.');
        }

        try {
            return DB::transaction(function () use ($v, $request) {
                $calc = $this->calculateTotals($v['items'], $v['discount_amount'] ?? 0, (float) $v['paid_amount']);
                $prescription = Prescription::findOrFail($v['prescription_id']);

                $sale = Sale::create([
                    'kasir_id' => auth()->id(),
                    'prescription_id' => $prescription->id,
                    'sale_type' => 'resep',
                    'total_amount' => $calc['finalTotal'],
                    'discount_amount' => $calc['discount'],
                    'paid_amount' => $v['paid_amount'],
                    'change_amount' => $calc['change'],
                    'payment_method' => $v['payment_method'],
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                foreach ($v['items'] as $itemData) {
                    $pItem = PrescriptionItem::findOrFail($itemData['prescription_item_id']);
                    $itemName = $pItem->item_type === 'obat_jadi'
                        ? ($pItem->medicine->nama_obat ?? 'Unknown Obat')
                        : ($pItem->compoundedProduct->nama_produk ?? $pItem->custom_formula_name);

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'prescription_item_id' => $pItem->id,
                        'item_type' => $pItem->item_type,
                        'medicine_id' => $pItem->medicine_id,
                        'compounded_product_id' => $pItem->compounded_product_id,
                        'item_name' => $itemName,
                        'quantity' => $itemData['quantity'],
                        'unit' => $pItem->unit,
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemData['unit_price'] * $itemData['quantity'],
                    ]);

                    if ($pItem->item_type === 'obat_jadi' && $pItem->medicine_id) {
                        $this->deductKasirStock((int) $pItem->medicine_id, (float) $itemData['quantity'], $sale, (int) auth()->id(), 'resep');
                    }
                    $pItem->update(['status' => 'selesai']);
                }

                $prescription->update(['status' => 'selesai']);
                AuditTrail::log($request->user()->id, 'sale.created', "Transaksi {$sale->sale_number} senilai {$sale->total_amount} oleh kasir", Sale::class, $sale->id);

                return redirect()->route('control.kasir.sales.receipt', $sale->id)->with('success', 'Transaksi berhasil disimpan.');
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receipt($saleId)
    {
        return Inertia::render('Control/Kasir/Receipt', [
            'sale' => Sale::with(['kasir', 'prescription.patient', 'saleItems.medicine', 'saleItems.compoundedProduct'])->findOrFail($saleId)
        ]);
    }

    public function otcIndex(Request $request)
    {
        $kasirId = auth()->id();

        $medicines = Medicine::with([
            'category',
            'incomingStocks' => fn($q) => $q->latest(),
            'warehouseInventories' => fn($q) => $q->where('location_type', 'kasir')->where('location_id', $kasirId)->where('stock_on_hand', '>', 0)->where('status', 'active')->where('is_frozen', false)->orderBy('expired_date', 'asc')
        ])->whereHas('warehouseInventories', fn($q) => $q->where('location_type', 'kasir')->where('location_id', $kasirId)->where('stock_on_hand', '>', 0)->where('status', 'active')->where('is_frozen', false))
        ->orderBy('nama_obat')
        ->get()
        ->map(function ($med) {
            $med->stok_kasir = (float) $med->warehouseInventories->sum('stock_on_hand');
            $hargaJual = (float) $med->selling_price;
            if ($hargaJual <= 0 && $med->incomingStocks->isNotEmpty()) {
                $hargaJual = (float) $med->incomingStocks->first()->harga_jual_akhir;
            }
            $med->selling_price = $hargaJual;
            $med->satuan = $med->satuan_stok ?: ($med->base_unit ?: 'Pcs');
            return $med;
        });

        $compoundedProducts = CompoundedProduct::where('status', 'active')->where('pricing_status', 'priced')->whereNotNull('harga_jual')->with('submission')->orderBy('nama_produk')->get();

        return inertia('Control/Kasir/OTC', compact('medicines', 'compoundedProducts'));
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
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            return DB::transaction(function () use ($v, $request) {
                $calc = $this->calculateTotals($v['items'], $v['discount_amount'] ?? 0, (float) $v['paid_amount']);

                $sale = Sale::create([
                    'kasir_id' => auth()->id(),
                    'prescription_id' => null,
                    'sale_type' => 'otc',
                    'total_amount' => $calc['finalTotal'],
                    'discount_amount' => $calc['discount'],
                    'paid_amount' => $v['paid_amount'],
                    'change_amount' => $calc['change'],
                    'payment_method' => $v['payment_method'],
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                foreach ($v['items'] as $itemData) {
                    $medicineId = null;
                    $compoundedProductId = null;

                    if ($itemData['item_type'] === 'obat_jadi') {
                        $medicine = Medicine::findOrFail($itemData['id']);
                        $itemName = $medicine->nama_obat;
                        $medicineId = $medicine->id;
                        $unit = $medicine->satuan_stok;
                    } else {
                        $product = CompoundedProduct::findOrFail($itemData['id']);
                        $itemName = $product->nama_produk;
                        $compoundedProductId = $product->id;
                        $unit = 'Paket';
                    }

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'prescription_item_id' => null,
                        'item_type' => $itemData['item_type'],
                        'medicine_id' => $medicineId,
                        'compounded_product_id' => $compoundedProductId,
                        'item_name' => $itemName,
                        'quantity' => $itemData['quantity'],
                        'unit' => $unit,
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemData['unit_price'] * $itemData['quantity'],
                    ]);

                    if ($itemData['item_type'] === 'obat_jadi' && $medicineId) {
                        $this->deductKasirStock((int) $medicineId, (float) $itemData['quantity'], $sale, (int) auth()->id(), 'otc');
                    }
                }

                AuditTrail::log($request->user()->id, 'sale.created', "Transaksi OTC {$sale->sale_number} senilai {$sale->total_amount} oleh kasir", Sale::class, $sale->id);

                return redirect()->route('control.kasir.sales.receipt', $sale->id)->with('success', 'Transaksi OTC berhasil disimpan.');
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
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
        $inventories = WarehouseInventory::where('medicine_id', $medicineId)
            ->kasir($userId)
            ->where('stock_on_hand', '>', 0)
            ->where('status', 'active')
            ->where('is_frozen', false)
            ->orderBy('expired_date', 'asc')
            ->lockForUpdate()
            ->get();

        $remaining = $qtyNeeded;
        foreach ($inventories as $inv) {
            if ($remaining <= 0) break;

            $qtyToDeduct = min($inv->stock_on_hand, $remaining);
            $inv->stock_on_hand -= $qtyToDeduct;
            $inv->save();

            if ($inv->incoming_stock_id) {
                $incoming = IncomingStock::find($inv->incoming_stock_id);
                if ($incoming) {
                    $incoming->qty_converted = max(0, $incoming->qty_converted - $qtyToDeduct);
                    $incoming->saveQuietly();
                }
            }

            AuditTrail::log($userId, "stock.deducted_by_sale_{$context}", "Stok obat dikurangi {$qtyToDeduct} via transaksi {$sale->id}", WarehouseInventory::class, $inv->id);

            $remaining -= $qtyToDeduct;
        }

        if ($remaining > 0) {
            $medName = Medicine::find($medicineId)?->nama_obat ?? "ID {$medicineId}";
            throw new \Exception("Stok tidak mencukupi untuk obat: {$medName}");
        }
    }
}
