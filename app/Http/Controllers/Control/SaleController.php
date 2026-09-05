<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Control\WarehouseInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $prescriptions = Prescription::with(['patient', 'prescriptionItems'])
            ->where('status', 'siap_ambil')
            ->orderBy('updated_at', 'asc')
            ->get();

        $selectedDate = $request->query('date', today()->format('Y-m-d'));

        $sales = Sale::with(['prescription.patient'])
            ->where('kasir_id', auth()->id())
            ->whereDate('created_at', $selectedDate)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Control/Kasir/Queue', [
            'prescriptions' => $prescriptions,
            'sales' => $sales,
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
            return redirect()->route('control.kasir.queue')
                ->with('error', 'Resep ini sudah selesai diproses.');
        }

        if ($prescription->status !== 'siap_ambil') {
            return redirect()->route('control.kasir.queue')
                ->with('error', 'Resep belum siap untuk diproses pembayaran.');
        }

        return Inertia::render('Control/Kasir/Checkout', [
            'prescription' => $prescription,
        ]);
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

        // Guard: Resep tidak dibayar dua kali
        $existingSale = Sale::where('prescription_id', $v['prescription_id'])
            ->where('status', 'paid')
            ->first();
            
        if ($existingSale) {
            return redirect()->back()->with('error', 'Resep ini sudah pernah dibayar. Tidak dapat diproses ulang.');
        }

        try {
            return DB::transaction(function () use ($v, $request) {
                $prescription = Prescription::findOrFail($v['prescription_id']);
                
                // 1. Calculate subtotal
                $subtotal = 0;
                foreach ($v['items'] as $item) {
                    $subtotal += ($item['unit_price'] * $item['quantity']);
                }

                // 2. Calculate discount and final total
                $discount = isset($v['discount_amount']) ? floatval($v['discount_amount']) : 0;
                if ($discount > $subtotal) {
                    return back()->with('error', 'Diskon tidak boleh lebih besar dari total belanja.');
                }
                $finalTotal = $subtotal - $discount;

                // 3. Validate paid amount
                if ($v['paid_amount'] < $finalTotal) {
                    return back()->with('error', 'Jumlah yang dibayarkan kurang dari total tagihan.');
                }

                // 4. Calculate change
                $change = $v['paid_amount'] - $finalTotal;

                // 5. Create Sale
                $sale = Sale::create([
                    'kasir_id' => auth()->id(),
                    'prescription_id' => $prescription->id,
                    'sale_type' => 'resep',
                    'total_amount' => $finalTotal,
                    'discount_amount' => $discount,
                    'paid_amount' => $v['paid_amount'],
                    'change_amount' => $change,
                    'payment_method' => $v['payment_method'],
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                // 5. Items and FEFO
                foreach ($v['items'] as $itemData) {
                    $prescriptionItem = PrescriptionItem::findOrFail($itemData['prescription_item_id']);
                    
                    $itemName = $prescriptionItem->item_type === 'obat_jadi' 
                        ? ($prescriptionItem->medicine->nama_obat ?? 'Unknown Obat') 
                        : ($prescriptionItem->compoundedProduct->nama_produk ?? $prescriptionItem->custom_formula_name);

                    $saleItem = SaleItem::create([
                        'sale_id' => $sale->id,
                        'prescription_item_id' => $prescriptionItem->id,
                        'item_type' => $prescriptionItem->item_type,
                        'medicine_id' => $prescriptionItem->medicine_id,
                        'compounded_product_id' => $prescriptionItem->compounded_product_id,
                        'item_name' => $itemName,
                        'quantity' => $itemData['quantity'],
                        'unit' => $prescriptionItem->unit,
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemData['unit_price'] * $itemData['quantity'],
                    ]);

                    // FEFO Deduction for obat_jadi
                    if ($prescriptionItem->item_type === 'obat_jadi' && $prescriptionItem->medicine_id) {
                        $qtyNeeded = $itemData['quantity'];
                        
                        $inventories = WarehouseInventory::where('medicine_id', $prescriptionItem->medicine_id)
                            ->kasir(auth()->id())
                            ->where('stock_on_hand', '>', 0)
                            ->where('status', 'active')
                            ->where('is_frozen', false)
                            ->orderBy('expired_date', 'asc')
                            ->lockForUpdate()
                            ->get();

                        foreach ($inventories as $inv) {
                            if ($qtyNeeded <= 0) break;
                            
                            $qtyToDeduct = min($inv->stock_on_hand, $qtyNeeded);
                            $inv->stock_on_hand -= $qtyToDeduct;
                            $inv->save();

                            // Sync deduction to IncomingStock (katalog faktur)
                            if ($inv->incoming_stock_id) {
                                $incoming = \App\Models\Control\IncomingStock::find($inv->incoming_stock_id);
                                if ($incoming) {
                                    $incoming->qty_converted = max(0, $incoming->qty_converted - $qtyToDeduct);
                                    $incoming->saveQuietly();
                                }
                            }
                            
                            \App\Models\Control\AuditTrail::log(
                                $request->user()->id, 
                                'stock.deducted_by_sale', 
                                "Stok {$itemName} dikurangi {$qtyToDeduct} via transaksi {$sale->id}", 
                                WarehouseInventory::class, 
                                $inv->id
                            );
                            
                            $qtyNeeded -= $qtyToDeduct;
                        }

                        if ($qtyNeeded > 0) {
                            throw new \Exception('Stok tidak mencukupi untuk obat: ' . $itemName);
                        }
                    }
                    
                    $prescriptionItem->update(['status' => 'selesai']);
                }

                $prescription->update(['status' => 'selesai']);

                \App\Models\Control\AuditTrail::log(
                    $request->user()->id,
                    'sale.created',
                    "Transaksi {$sale->sale_number} senilai {$sale->total_amount} oleh kasir",
                    Sale::class,
                    $sale->id
                );

                return redirect()->route('control.kasir.sales.receipt', $sale->id)->with('success', 'Transaksi berhasil disimpan.');
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receipt($saleId)
    {
        $sale = Sale::with([
            'kasir',
            'prescription.patient',
            'saleItems.medicine',
            'saleItems.compoundedProduct'
        ])->findOrFail($saleId);

        return Inertia::render('Control/Kasir/Receipt', [
            'sale' => $sale,
        ]);
    }
    public function otcIndex(Request $request)
    {
        $kasirId = auth()->id();

        // Ambil obat yang memiliki stok fisik aktif di lokasi kasir ini (hasil distribusi dari Gudang/Admin)
        $medicines = \App\Models\Control\Medicine::with([
            'category',
            'incomingStocks' => function($q) {
                $q->orderBy('created_at', 'desc');
            },
            'warehouseInventories' => function($q) use ($kasirId) {
                $q->where('location_type', 'kasir')
                  ->where('location_id', $kasirId)
                  ->where('stock_on_hand', '>', 0)
                  ->where('status', 'active')
                  ->where('is_frozen', false)
                  ->orderBy('expired_date', 'asc');
            }
        ])->whereHas('warehouseInventories', function($q) use ($kasirId) {
            $q->where('location_type', 'kasir')
              ->where('location_id', $kasirId)
              ->where('stock_on_hand', '>', 0)
              ->where('status', 'active')
              ->where('is_frozen', false);
        })
        ->orderBy('nama_obat')
        ->get()
        ->map(function ($medicine) use ($kasirId) {
            $stokKasir = (float) $medicine->warehouseInventories->sum('stock_on_hand');
            $medicine->stok_kasir = $stokKasir;
            
            // Harga Jual fallback jika belum di-set di master obat
            $hargaJual = (float) $medicine->selling_price;
            if ($hargaJual <= 0 && $medicine->incomingStocks->isNotEmpty()) {
                $hargaJual = (float) $medicine->incomingStocks->first()->harga_jual_akhir;
            }
            $medicine->selling_price = $hargaJual;
            $medicine->satuan = $medicine->satuan_stok ?: ($medicine->base_unit ?: 'Pcs');

            return $medicine;
        });

        $compoundedProducts = \App\Models\Control\CompoundedProduct::where('status', 'active')
            ->where('pricing_status', 'priced')
            ->whereNotNull('harga_jual')
            ->with('submission')
            ->orderBy('nama_produk')
            ->get();

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
                // 1. Calculate subtotal
                $subtotal = 0;
                foreach ($v['items'] as $item) {
                    $subtotal += ($item['unit_price'] * $item['quantity']);
                }

                // 2. Calculate discount and final total
                $discount = isset($v['discount_amount']) ? floatval($v['discount_amount']) : 0;
                if ($discount > $subtotal) {
                    return back()->with('error', 'Diskon tidak boleh lebih besar dari total belanja.');
                }
                $finalTotal = $subtotal - $discount;

                // 3. Validate paid amount
                if ($v['paid_amount'] < $finalTotal) {
                    return back()->with('error', 'Uang yang diterima kurang dari total tagihan.');
                }

                // 4. Calculate change
                $change = $v['paid_amount'] - $finalTotal;

                // 5. Create Sale
                $sale = Sale::create([
                    'kasir_id' => auth()->id(),
                    'prescription_id' => null,
                    'sale_type' => 'otc',
                    'total_amount' => $finalTotal,
                    'discount_amount' => $discount,
                    'paid_amount' => $v['paid_amount'],
                    'change_amount' => $change,
                    'payment_method' => $v['payment_method'],
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                // 5. Items and FEFO
                foreach ($v['items'] as $itemData) {
                    $itemName = '';
                    $medicineId = null;
                    $compoundedProductId = null;
                    $unit = '';
                    
                    if ($itemData['item_type'] === 'obat_jadi') {
                        $medicine = \App\Models\Control\Medicine::findOrFail($itemData['id']);
                        $itemName = $medicine->nama_obat;
                        $medicineId = $medicine->id;
                        $unit = $medicine->satuan_stok;
                    } else {
                        $product = \App\Models\Control\CompoundedProduct::findOrFail($itemData['id']);
                        $itemName = $product->nama_produk;
                        $compoundedProductId = $product->id;
                        $unit = 'Paket';
                    }

                    $saleItem = SaleItem::create([
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

                    // FEFO Deduction for obat_jadi
                    if ($itemData['item_type'] === 'obat_jadi' && $medicineId) {
                        $qtyNeeded = $itemData['quantity'];
                        
                        $inventories = WarehouseInventory::where('medicine_id', $medicineId)
                            ->kasir(auth()->id())
                            ->where('stock_on_hand', '>', 0)
                            ->where('status', 'active')
                            ->where('is_frozen', false)
                            ->orderBy('expired_date', 'asc')
                            ->lockForUpdate()
                            ->get();

                        foreach ($inventories as $inv) {
                            if ($qtyNeeded <= 0) break;
                            
                            $qtyToDeduct = min($inv->stock_on_hand, $qtyNeeded);
                            $inv->stock_on_hand -= $qtyToDeduct;
                            $inv->save();

                            // Sync deduction to IncomingStock (katalog faktur)
                            if ($inv->incoming_stock_id) {
                                $incoming = \App\Models\Control\IncomingStock::find($inv->incoming_stock_id);
                                if ($incoming) {
                                    $incoming->qty_converted = max(0, $incoming->qty_converted - $qtyToDeduct);
                                    $incoming->saveQuietly();
                                }
                            }
                            
                            \App\Models\Control\AuditTrail::log(
                                $request->user()->id, 
                                'stock.deducted_by_sale_otc', 
                                "Stok {$itemName} dikurangi {$qtyToDeduct} via transaksi OTC {$sale->id}", 
                                WarehouseInventory::class, 
                                $inv->id
                            );
                            
                            $qtyNeeded -= $qtyToDeduct;
                        }

                        if ($qtyNeeded > 0) {
                            throw new \Exception('Stok tidak mencukupi untuk obat: ' . $itemName);
                        }
                    }
                }

                \App\Models\Control\AuditTrail::log(
                    $request->user()->id,
                    'sale.created',
                    "Transaksi OTC {$sale->sale_number} senilai {$sale->total_amount} oleh kasir",
                    Sale::class,
                    $sale->id
                );

                return redirect()->route('control.kasir.sales.receipt', $sale->id)->with('success', 'Transaksi OTC berhasil disimpan.');
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
