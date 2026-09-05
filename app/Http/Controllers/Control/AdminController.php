<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\CompoundingFormula;
use App\Models\Control\CompoundingSubmission;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\ReturnForm;
use App\Models\Control\Setting;

use App\Models\Control\RawMaterialStock;
use App\Models\Control\Supplier;
use App\Models\Control\WarehouseInventory;
use App\Models\Control\StockLog;
use App\Models\Control\StockMutation;
use App\Models\Control\StockOpnameItem;
use App\Models\User;
use App\Services\IncomingStockService;
use App\Services\StockOpnameService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Control\StockOpname;
use App\Http\Requests\Control\SetMedicinePriceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Admin Dashboard - The Brain of the system.
     */
    public function dashboard(Request $request)
    {
        $settings = Setting::current();
        $totalMedicines = Medicine::count();
        $lowStockMedicines = Medicine::belowThreshold()->get();
        $fefoAlertCount = WarehouseInventory::active()->where('stock_on_hand', '>', 0)->whereNotNull('expired_date')->expiringSoon(90)->count();
        $fefoNearestBatch = WarehouseInventory::active()->where('stock_on_hand', '>', 0)->whereNotNull('expired_date')->orderBy('expired_date', 'asc')->first();

        $pendingCompounding = CompoundingSubmission::pending()->count();

        $todaySalesCount = \App\Models\Sale::where('status', 'paid')->whereDate('created_at', today())->count();
        $todaySalesTotal = \App\Models\Sale::where('status', 'paid')->whereDate('created_at', today())->sum('total_amount');
        $activePrescriptionsCount = \App\Models\Prescription::whereIn('status', ['diproses', 'siap_ambil'])->count();
        $pendingPricingCount = CompoundedProduct::where('pricing_status', 'pending_price')->count();

        $lowStockPercentage = ($totalMedicines > 0) ? round(($lowStockMedicines->count() / $totalMedicines) * 100) : 0;

        $filter = $request->query('filter', 'abjad');
        $query = \App\Models\Control\Medicine::with(['incomingStocks' => function($q) {
            $q->orderBy('expired_date', 'asc');
        }]);
        
        if ($filter === 'exp') {
            $query->orderBy(
                \App\Models\Control\IncomingStock::select('expired_date')
                    ->whereColumn('medicine_id', 'medicines.id')
                    ->whereNotNull('expired_date')
                    ->orderBy('expired_date', 'asc')
                    ->limit(1),
                'asc'
            );
        } elseif ($filter === 'qty') {
            $query->orderBy('stok_total', 'asc');
        } else {
            $query->orderBy('nama_obat', 'asc');
        }
        
        $paginator = $query->paginate(10)->withQueryString();
        
        $tableStocks = $paginator->getCollection()->map(function ($medicine) {
            $incoming = $medicine->incomingStocks->first(); // Gets the one with earliest expiry
            return [
                'id' => $medicine->id,
                'nama_obat' => $medicine->nama_obat,
                'kategori' => $medicine->kategori ?: '-',
                'nomor_batch' => $incoming ? $incoming->batch_no : '-',
                'stok_fisik' => $medicine->stok_total,
                
                // Required for Katalog Faktur QTY formatting logic
                'qty_masuk' => $incoming ? $incoming->qty_masuk : 0,
                'qty_converted' => $incoming ? $incoming->qty_converted : 0,
                'satuan_input' => $incoming ? $incoming->satuan_input : null,
                'mid_unit' => $incoming ? $incoming->mid_unit : null,
                'unit_conversion_factor' => $incoming ? $incoming->unit_conversion_factor : 1,
                'mid_to_big_ratio' => $incoming ? $incoming->mid_to_big_ratio : 0,
                'small_to_mid_ratio' => $incoming ? $incoming->small_to_mid_ratio : 0,
                'satuan_stok' => $medicine->satuan_stok ?? 'Unit',
                
                'batas_aman' => $medicine->threshold_min,
                'expired_date' => $incoming && $incoming->expired_date ? $incoming->expired_date->format('d M Y') : '-',
                'is_expiring_soon' => $incoming && $incoming->expired_date && $incoming->expired_date->diffInDays(now()) <= 90
            ];
        });
        
        $paginator->setCollection($tableStocks);

        return Inertia::render('Control/Admin/Dashboard', [
            'stats' => [
                'total_obat' => $totalMedicines,
                'low_stock_count' => $lowStockMedicines->count(),
                'low_stock_percentage' => $lowStockPercentage,
                'fefo_alert_count' => $fefoAlertCount,
                'fefo_nearest_date' => $fefoNearestBatch ? $fefoNearestBatch->expired_date->format('d M Y') : null,
                'pending_compounding' => $pendingCompounding,
                'today_sales_count' => $todaySalesCount,
                'today_sales_total' => $todaySalesTotal,
                'active_prescriptions_count' => $activePrescriptionsCount,
                'pending_pricing_count' => $pendingPricingCount,
            ],
            'tableStocks' => $paginator,
            'currentFilter' => $filter,
        ]);
    }

    // ─── SETTINGS ───────────────────────────────────────────
    public function settings()
    {
        return Inertia::render('Control/Admin/Settings', [
            'settings' => Setting::current(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'default_ppn' => 'required|numeric|min:0|max:100',
            'default_margin' => 'required|numeric|min:0|max:100',
        ]);

        $settings = Setting::current();
        $old = $settings->toArray();
        $settings->update($validated);

        AuditTrail::log(
            $request->user()->id,
            'UPDATE_SETTINGS',
            'Mengubah pengaturan default PPN & Margin',
            Setting::class,
            $settings->id,
            $old,
            $validated
        );

        return redirect()->back()->with('success', 'Pengaturan berhasil diperbarui.');
    }

    // ─── SUPPLIERS ──────────────────────────────────────────
    public function suppliers()
    {
        $suppliers = Supplier::latest()->paginate(15);

        return Inertia::render('Control/Admin/Suppliers', [
            'suppliers' => $suppliers,
        ]);
    }

    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'nama_pbf' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:500',
            'telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
        ]);

        $supplier = Supplier::create($validated);

        AuditTrail::log(
            $request->user()->id,
            'CREATE_SUPPLIER',
            "Menambah supplier: {$supplier->nama_pbf}",
            Supplier::class,
            $supplier->id
        );

        return redirect()->back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'nama_pbf' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:500',
            'telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
        ]);

        $old = $supplier->toArray();
        $supplier->update($validated);

        AuditTrail::log(
            $request->user()->id,
            'UPDATE_SUPPLIER',
            "Mengubah supplier: {$supplier->nama_pbf}",
            Supplier::class,
            $supplier->id,
            $old,
            $validated
        );

        return redirect()->back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroySupplier(Request $request, Supplier $supplier)
    {
        $name = $supplier->nama_pbf;
        $supplier->delete();

        AuditTrail::log(
            $request->user()->id,
            'DELETE_SUPPLIER',
            "Menghapus supplier: {$name}"
        );

        return redirect()->back()->with('success', 'Supplier berhasil dihapus.');
    }

    // ─── MEDICINES (MASTER OBAT FAKTUR) ─────────────────────
    public function medicinesFaktur(Request $request)
    {
        $search = $request->input('search');

        // 1. Katalog Faktur (Data Faktur Masuk yang valid)
        $query = IncomingStock::with(['medicine', 'supplier', 'warehouseInventories'])
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('medicine', function ($q2) use ($search) {
                    $q2->where('nama_obat', 'like', "%{$search}%");
                })->orWhere('batch_no', 'like', "%{$search}%");
            });
        }

        $fakturStocks = $query->paginate(15)
            ->withQueryString()
            ->through(function ($stock) {
                // Sisa stok riil mencerminkan stok fisik di Gudang + Kasir (berelasi dengan retur dan penjualan)
                $liveStock = (float) $stock->warehouseInventories->sum('stock_on_hand');
                $stock->qty_converted = $liveStock;

                $hppKecil = $stock->calculateHppSatuan();
                $sellingKecil = $stock->medicine ? $stock->medicine->selling_price : 0;

                $prices = [];
                $baseUnit = $stock->medicine ? $stock->medicine->satuan_stok : 'Pcs';

                // KECIL (Base Unit) is ALWAYS present
                $prices['kecil'] = [
                    'unit' => $baseUnit,
                    'hpp' => $hppKecil,
                    'selling' => $sellingKecil,
                    'profit' => ($hppKecil > 0 && $sellingKecil > 0) ? round((($sellingKecil - $hppKecil) / $hppKecil) * 100, 2) : 0
                ];

                // MENENGAH & BESAR Logic
                if ($stock->mid_unit) {
                    $hppMenengah = $hppKecil * ($stock->small_to_mid_ratio ?: 1);
                    $sellingMenengah = $stock->medicine ? $stock->medicine->selling_price_mid : 0;
                    $prices['menengah'] = [
                        'unit' => $stock->mid_unit,
                        'hpp' => $hppMenengah,
                        'selling' => $sellingMenengah,
                        'profit' => ($hppMenengah > 0 && $sellingMenengah > 0) ? round((($sellingMenengah - $hppMenengah) / $hppMenengah) * 100, 2) : 0
                    ];

                    if ($stock->satuan_input && strtolower($stock->satuan_input) !== strtolower($stock->mid_unit)) {
                        $hppBesar = $hppMenengah * ($stock->mid_to_big_ratio ?: 1);
                        $sellingBesar = $stock->medicine ? $stock->medicine->selling_price_large : 0;
                        $prices['besar'] = [
                            'unit' => $stock->satuan_input,
                            'hpp' => $hppBesar,
                            'selling' => $sellingBesar,
                            'profit' => ($hppBesar > 0 && $sellingBesar > 0) ? round((($sellingBesar - $hppBesar) / $hppBesar) * 100, 2) : 0
                        ];
                    }
                } else {
                    if ($stock->satuan_input && strtolower($stock->satuan_input) !== strtolower($baseUnit)) {
                        $hppBesar = $hppKecil * ($stock->unit_conversion_factor ?: 1);
                        $sellingBesar = $stock->medicine ? $stock->medicine->selling_price_large : 0;
                        $prices['besar'] = [
                            'unit' => $stock->satuan_input,
                            'hpp' => $hppBesar,
                            'selling' => $sellingBesar,
                            'profit' => ($hppBesar > 0 && $sellingBesar > 0) ? round((($sellingBesar - $hppBesar) / $hppBesar) * 100, 2) : 0
                        ];
                    }
                }

                $orderedPrices = [];
                if (isset($prices['besar']))
                    $orderedPrices['besar'] = $prices['besar'];
                if (isset($prices['menengah']))
                    $orderedPrices['menengah'] = $prices['menengah'];
                $orderedPrices['kecil'] = $prices['kecil'];

                $stock->prices = $orderedPrices;

                // Dynamic structured kemasan_tersedia mapping for multi-layer packaging Accordion
                $kemasanTersedia = [];
                if (isset($orderedPrices['besar'])) {
                    $totalKonversiBesar = $stock->mid_unit 
                        ? (($stock->mid_to_big_ratio ?: 1) * ($stock->small_to_mid_ratio ?: 1))
                        : ($stock->unit_conversion_factor ?: 1);
                    $kemasanTersedia[] = [
                        'satuan' => $orderedPrices['besar']['unit'],
                        'isi_konversi' => (float) $totalKonversiBesar,
                        'tipe' => 'tersier',
                        'hpp' => $orderedPrices['besar']['hpp'],
                        'harga_jual' => $orderedPrices['besar']['selling'],
                        'margin_persen' => $orderedPrices['besar']['profit'],
                    ];
                }
                if (isset($orderedPrices['menengah'])) {
                    $kemasanTersedia[] = [
                        'satuan' => $orderedPrices['menengah']['unit'],
                        'isi_konversi' => (float) ($stock->small_to_mid_ratio ?: 1),
                        'tipe' => 'sekunder',
                        'hpp' => $orderedPrices['menengah']['hpp'],
                        'harga_jual' => $orderedPrices['menengah']['selling'],
                        'margin_persen' => $orderedPrices['menengah']['profit'],
                    ];
                }
                $kemasanTersedia[] = [
                    'satuan' => $orderedPrices['kecil']['unit'],
                    'isi_konversi' => 1,
                    'tipe' => 'primer',
                    'hpp' => $orderedPrices['kecil']['hpp'],
                    'harga_jual' => $orderedPrices['kecil']['selling'],
                    'margin_persen' => $orderedPrices['kecil']['profit'],
                ];
                $stock->kemasan_tersedia = $kemasanTersedia;

                $stock->calculated_hpp = $hppKecil;
                $stock->current_selling_price = $sellingKecil;
                $stock->profit_percentage = ($hppKecil > 0 && $sellingKecil > 0) ? round((($sellingKecil - $hppKecil) / $hppKecil) * 100, 2) : 0;

                return $stock;
            });

        return Inertia::render('Control/Admin/Medicines', [
            'fakturStocks' => $fakturStocks,
            'racikanProducts' => ['data' => [], 'total' => 0],
            'activeTab' => 'faktur',
            'title' => 'Katalog Obat dari Faktur',
            'filters' => ['search' => $search]
        ]);
    }

    public function medicinesRacikan(Request $request)
    {
        $search = $request->input('search');

        // 2. Katalog Racikan (Produk Racikan yang selesai diverifikasi)
        $query = CompoundedProduct::with(['submission.user', 'creator'])
            ->latest();

        if ($search) {
            $query->where('nama_produk', 'like', "%{$search}%");
        }

        $racikanProducts = $query->paginate(15)->withQueryString();

        return Inertia::render('Control/Admin/Medicines', [
            'fakturStocks' => ['data' => [], 'total' => 0],
            'racikanProducts' => $racikanProducts,
            'activeTab' => 'racikan',
            'title' => 'Katalog Racikan Apoteker',
            'filters' => ['search' => $search]
        ]);
    }

    /**
     * Transfer approved+verified stock from quarantine to active warehouse.
     * Auto-fills batch_no, expired_date, qty from the source faktur.
     * This is the ONLY way to add stock to the warehouse — no manual creation.
     */
    public function transferToWarehouse(Request $request)
    {
        $validated = $request->validate([
            'incoming_stock_id' => 'required|exists:incoming_stocks,id',
            'tanggal_memindahkan' => 'required|date',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $stock = IncomingStock::with('medicine')->findOrFail($validated['incoming_stock_id']);

            // Ensure this stock is approved and verified by gudang
            if ($stock->status_approval !== 'approved' || $stock->qty_fisik_verify === null) {
                return redirect()->back()->with('error', 'Faktur belum diverifikasi admin atau belum di-approve.');
            }

            $qtyToTransfer = $stock->qty_fisik_verify;

            // Pastikan pencarian menggunakan where('medicine_id', ...)->where('batch_no', ...)->first()
            $wi = WarehouseInventory::where('medicine_id', $stock->medicine_id)
                ->where('batch_no', $stock->batch_no)
                ->first();

            if ($wi) {
                // Set context before updating!
                $wi->setMutationContext('in', $stock, "Transfer Tambah Stok Faktur: {$stock->medicine->nama_obat} batch {$stock->batch_no} (Faktur: {$stock->faktur_no})");
                // Jika batch sudah ada, update kuantitas secara presisi sesuai nilai riil yang dikirim
                $wi->update([
                    'stock_on_hand' => $qtyToTransfer,
                    'transferred_at' => $validated['tanggal_memindahkan'],
                    'incoming_stock_id' => $stock->id,
                    'status' => 'active',
                ]);
            } else {
                // Jika belum ada, buat record batch baru.
                $wi = new WarehouseInventory([
                    'medicine_id' => $stock->medicine_id,
                    'batch_no' => $stock->batch_no,
                    'incoming_stock_id' => $stock->id,
                    'expired_date' => $stock->expired_date,
                    'stock_on_hand' => $qtyToTransfer,
                    'transferred_at' => $validated['tanggal_memindahkan'],
                    'status' => 'active',
                ]);
                $wi->setMutationContext('in', $stock, "Transfer Masuk Stok Faktur Baru: {$stock->medicine->nama_obat} batch {$stock->batch_no} (Faktur: {$stock->faktur_no})");
                $wi->save();
            }

            $stock->medicine->recalculateStock();

            AuditTrail::log(
                $request->user()->id,
                'TRANSFER_TO_WAREHOUSE',
                "Pindahkan ke inventori: {$stock->medicine->nama_obat} batch {$stock->batch_no} qty {$qtyToTransfer} {$stock->medicine->satuan_stok} (Faktur: {$stock->faktur_no})",
                WarehouseInventory::class,
                $wi->id
            );

            return redirect()->back()->with('success', "Berhasil memindahkan {$qtyToTransfer} {$stock->medicine->satuan_stok} {$stock->medicine->nama_obat} ke inventori.");
        });
    }

    /**
     * Stock adjustment (In/Out) with mandatory reason for audit trail.
     * This is the ONLY way to manually modify stock — stok_total is read-only.
     */
    public function adjustStock(Request $request)
    {
        $validated = $request->validate([
            'warehouse_inventory_id' => 'required|exists:warehouse_inventories,id',
            'type' => 'required|in:in,out',
            'qty' => 'required|integer|min:1',
            'alasan' => 'required|string|max:1000',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $wi = WarehouseInventory::with('medicine')->findOrFail($validated['warehouse_inventory_id']);

            if ($validated['type'] === 'out' && $wi->stock_on_hand < $validated['qty']) {
                return redirect()->back()->with('error', 'Stok tidak mencukupi untuk pengurangan.');
            }

            $adjustment = StockAdjustment::create([
                'medicine_id' => $wi->medicine_id,
                'warehouse_inventory_id' => $wi->id,
                'type' => $validated['type'],
                'qty' => $validated['qty'],
                'alasan' => $validated['alasan'],
                'adjusted_by' => $request->user()->id,
            ]);

            if ($validated['type'] === 'in') {
                $wi->increment('stock_on_hand', $validated['qty']);
            } else {
                $wi->decrement('stock_on_hand', $validated['qty']);
            }

            $wi->medicine->recalculateStock();

            $typeLabel = $validated['type'] === 'in' ? 'Masuk' : 'Keluar';
            AuditTrail::log(
                $request->user()->id,
                'STOCK_ADJUSTMENT',
                "Penyesuaian stok {$typeLabel}: {$wi->medicine->nama_obat} batch {$wi->batch_no} qty {$validated['qty']} — Alasan: {$validated['alasan']}",
                StockAdjustment::class,
                $adjustment->id,
                ['stock_before' => $wi->stock_on_hand + ($validated['type'] === 'out' ? $validated['qty'] : -$validated['qty'])],
                ['stock_after' => $wi->stock_on_hand]
            );

            return redirect()->back()->with('success', "Penyesuaian stok {$typeLabel} berhasil dicatat.");
        });
    }


    // ─── COMPOUNDED PRODUCTS (Master Obat Racikan) ──────────
    public function compoundedProducts()
    {
        $products = CompoundedProduct::with(['submission.user', 'creator'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Control/Admin/CompoundedProducts', [
            'products' => $products,
        ]);
    }

    // ─── RAW MATERIALS (Master Bahan Baku — Admin View) ─────
    public function rawMaterials()
    {
        $materials = RawMaterial::with([
            'category',
            'stocks' => function ($q) {
                $q->where('stock_on_hand', '>', 0)->orderBy('expired_date');
            }
        ])
            ->where('status', 'active')
            ->latest()
            ->paginate(15);

        return Inertia::render('Control/Admin/RawMaterials', [
            'materials' => $materials,
        ]);
    }

    public function storeRawMaterial(Request $request)
    {
        $v = $request->validate([
            'nama_bahan' => 'required|string|max:255',
            'bentuk_sediaan' => 'required|string|max:100',
            'kode_bahan' => 'nullable|string|max:50|unique:raw_materials,kode_bahan',
            'satuan_ukur' => 'required|string|max:50',
            'expected_quantity' => 'required|numeric|min:0',
            'batch_no' => 'nullable|string|max:100',
            'expired_date' => 'required|date',
            'harga_beli_satuan' => 'required|numeric|min:0',
            'threshold_min' => 'required|numeric|min:0',
            'deskripsi' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($v, $request) {
            $rm = RawMaterial::create([
                'nama_bahan' => $v['nama_bahan'],
                'bentuk_sediaan' => $v['bentuk_sediaan'],
                'kode_bahan' => $v['kode_bahan'],
                'satuan_ukur' => $v['satuan_ukur'],
                'harga_beli_satuan' => $v['harga_beli_satuan'],
                'threshold_min' => $v['threshold_min'],
                'deskripsi' => $v['deskripsi'],
                'status' => 'active',
                'creator_id' => $request->user()->id,
                'stok_total' => $v['expected_quantity'],
            ]);

            RawMaterialStock::create([
                'raw_material_id' => $rm->id,
                'batch_no' => $v['batch_no'] ?? 'BATCH-' . strtoupper(\Illuminate\Support\Str::random(5)),
                'expected_quantity' => $v['expected_quantity'],
                'actual_quantity' => $v['expected_quantity'],
                'stock_on_hand' => $v['expected_quantity'],
                'expired_date' => $v['expired_date'],
                'status' => 'active',
                'notes' => 'Registrasi awal oleh Admin.',
            ]);

            AuditTrail::log($request->user()->id, 'CREATE_RAW_MATERIAL', "Admin menambah bahan baku: {$rm->nama_bahan}", RawMaterial::class, $rm->id);

            return redirect()->back()->with('success', 'Bahan baku berhasil ditambahkan dan stok langsung aktif.');
        });
    }

    public function updateRawMaterial(Request $request, RawMaterial $rawMaterial)
    {
        $v = $request->validate([
            'nama_bahan' => 'required|string|max:255',
            'bentuk_sediaan' => 'required|string|max:100',
            'kode_bahan' => 'nullable|string|max:50|unique:raw_materials,kode_bahan,' . $rawMaterial->id,
            'satuan_ukur' => 'required|string|max:50',
            'harga_beli_satuan' => 'required|numeric|min:0',
            'threshold_min' => 'required|numeric|min:0',
            'deskripsi' => 'nullable|string|max:1000',
        ]);

        $rawMaterial->update($v);
        AuditTrail::log($request->user()->id, 'UPDATE_RAW_MATERIAL', "Mengubah bahan baku: {$rawMaterial->nama_bahan}", RawMaterial::class, $rawMaterial->id);
        return redirect()->back()->with('success', 'Bahan baku berhasil diperbarui.');
    }

    public function destroyRawMaterial(Request $request, RawMaterial $rawMaterial)
    {
        $name = $rawMaterial->nama_bahan;
        $rawMaterial->delete();
        AuditTrail::log($request->user()->id, 'DELETE_RAW_MATERIAL', "Menghapus bahan baku: {$name}");
        return redirect()->back()->with('success', 'Bahan baku berhasil dihapus.');
    }

    /**
     * Update medicine photo.
     */
    public function updateMedicinePhoto(Request $request, Medicine $medicine)
    {
        $request->validate([
            'foto_obat' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096'
        ]);

        if ($request->hasFile('foto_obat')) {
            $file = $request->file('foto_obat');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            
            // Delete old photo if exists
            if ($medicine->foto_obat && \Illuminate\Support\Facades\Storage::disk('public')->exists('medicines/' . $medicine->foto_obat)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete('medicines/' . $medicine->foto_obat);
            }
            
            $file->storeAs('medicines', $filename, 'public');
            
            $medicine->foto_obat = $filename;
            $medicine->save();
        }

        return redirect()->back()->with('success', 'Foto obat berhasil diperbarui.');
    }

    /**
     * Update medicine metadata only (threshold, description). Stock is READ-ONLY.
     */
    public function updateMedicine(Request $request, Medicine $medicine)
    {
        $validated = $request->validate([
            'threshold_min' => 'required|integer|min:0',
            'deskripsi' => 'nullable|string|max:1000',
        ]);

        $old = $medicine->toArray();
        $medicine->update($validated);

        AuditTrail::log(
            $request->user()->id,
            'UPDATE_MEDICINE',
            "Mengubah pengaturan obat: {$medicine->nama_obat}",
            Medicine::class,
            $medicine->id,
            $old,
            $validated
        );

        return redirect()->back()->with('success', 'Pengaturan obat berhasil diperbarui.');
    }

    // ─── INCOMING STOCKS (FAKTUR) ───────────────────────────
    public function incomingStocks(Request $request)
    {
        $query = IncomingStock::with(['supplier', 'medicine', 'returnForm']);

        if ($search = $request->search) {
            $query->where('faktur_no', 'like', "%{$search}%")
                ->orWhereHas('supplier', function ($q) use ($search) {
                    $q->where('nama_pbf', 'like', "%{$search}%");
                });
        }

        $stocks = $query->latest()->paginate(15)->withQueryString();

        $suppliers = Supplier::active()->get();
        $medicines = Medicine::all()->map(function ($m) {
            $lastApproved = IncomingStock::where('medicine_id', $m->id)
                ->where('status_gate', 'active')
                ->latest()
                ->first();
            $m->current_price = $lastApproved ? $lastApproved->harga_jual_akhir : 0;
            return $m;
        });
        $settings = Setting::current();

        return Inertia::render('Control/Admin/IncomingStocks', [
            'stocks' => $stocks,
            'suppliers' => $suppliers,
            'medicines' => $medicines,
            'settings' => $settings,
            'filters' => $request->only('search'),
        ]);
    }

    public function storeIncomingStock(Request $request, IncomingStockService $service)
    {
        $validated = $request->validate([
            'faktur_no' => 'required|string|max:100',
            'supplier_id' => 'required|exists:suppliers,id',
            'tanggal_faktur' => 'nullable|date',
            'foto_faktur' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'nullable|exists:medicines,id',
            'items.*.is_new_medicine' => 'boolean',
            'items.*.new_medicine_name' => 'required_if:items.*.is_new_medicine,true|nullable|string|max:255',
            'items.*.new_medicine_kategori' => 'nullable|string|max:255',
            'items.*.new_medicine_satuan_besar' => 'nullable|string|max:50',
            'items.*.new_medicine_satuan_kecil' => 'required_if:items.*.is_new_medicine,true|nullable|in:Tube,Gram,Pot,Tablet,Kapsul,Botol,Ampul,Sachet,Strip,Box,Pcs',
            'items.*.new_medicine_rasio_konversi' => 'nullable|integer|min:1',
            'items.*.unit_conversion_factor' => 'nullable|integer|min:1',
            'items.*.satuan_input' => 'nullable|string|max:50',
            'items.*.mid_unit' => 'nullable|string|max:50',
            'items.*.mid_to_big_ratio' => 'nullable|integer|min:1',
            'items.*.small_to_mid_ratio' => 'nullable|integer|min:1',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expired_date' => 'nullable|date',
            'items.*.qty_masuk' => 'required|integer|min:1',
            'items.*.harga_beli_satuan' => 'required|numeric|min:0',
            'items.*.diskon_persen' => 'nullable|numeric|min:0|max:100',
        ]);

        $settings = Setting::current();
        $validated['ppn_persen'] = $settings->default_ppn ?? 11.00;

        $service->store($validated, $request->file('foto_faktur'), $request->user()->id);

        return redirect()->back()->with('success', 'Faktur berhasil disimpan dan stok langsung aktif.');
    }


    public function auditTrails(Request $request)
    {
        $query = AuditTrail::with('user')->latest('performed_at');

        // Advanced Filtering
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', "%{$request->search}%")
                    ->orWhere('action', 'like', "%{$request->search}%")
                    ->orWhereHas('user', function ($qu) use ($request) {
                        $qu->where('name', 'like', "%{$request->search}%");
                    });
            });
        }

        if ($request->role) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        return Inertia::render('Control/Admin/AuditTrail', [
            'audits' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'role'])
        ]);
    }

    public function stockLogs(Request $request)
    {
        $query = \App\Models\Control\StockMutation::with([
            'inventory.medicine', 
            'inventory.incomingStock', 
            'rawMaterialStock.rawMaterial', 
            'item'
        ])->where('quantity', '>', 0);

        if ($request->has('start_date') && $request->has('end_date') && $request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        if ($request->has('type') && $request->type !== 'all') {
            if ($request->type === 'Masuk') {
                $query->whereIn('type', ['Masuk', 'Penyesuaian Masuk']);
            } elseif ($request->type === 'Retur') {
                $query->where(function($q) {
                    $q->where('reference_type', 'App\Models\Control\ReturnForm')
                      ->orWhere('type', 'Retur');
                });
            } elseif ($request->type === 'Jual') {
                $query->where(function($q) {
                    $q->whereIn('type', ['Keluar', 'Penjualan', 'Penyesuaian Keluar'])
                      ->where(function($q2) {
                          $q2->whereNull('reference_type')
                             ->orWhere('reference_type', '!=', 'App\Models\Control\ReturnForm');
                      });
                });
            }
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('inventory.medicine', function ($q2) use ($search) {
                    $q2->where('nama_obat', 'like', "%{$search}%");
                })->orWhereHas('rawMaterialStock.rawMaterial', function ($q2) use ($search) {
                    $q2->where('nama_bahan', 'like', "%{$search}%");
                });
            });
        }

        $kpi_in = (clone $query)->whereIn('type', ['Masuk', 'Penyesuaian Masuk', 'Retur'])->count();
        $kpi_out = (clone $query)->whereIn('type', ['Keluar', 'Pemusnahan', 'Penjualan', 'Penyesuaian Keluar'])->count();

        $logs = $query->latest()->paginate(25)->withQueryString();
        
        return Inertia::render('Control/Admin/StockLogs', [
            'logs' => $logs,
            'kpi' => [
                'in' => $kpi_in,
                'out' => $kpi_out,
                'total' => $logs->total()
            ],
            'filters' => $request->only(['search', 'start_date', 'end_date', 'type'])
        ]);
    }

    // ─── USER MANAGEMENT ──────────────────────────────────────
    public function users()
    {
        return Inertia::render('Control/Admin/Users', [
            'users' => User::latest()->paginate(15),
        ]);
    }

    public function storeUser(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'role' => 'required|in:admin,apoteker,kasir',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $v['name'],
            'username' => $v['username'],
            'email' => $v['email'],
            'role' => $v['role'],
            'password' => \Illuminate\Support\Facades\Hash::make($v['password']),
        ]);

        AuditTrail::log($request->user()->id, 'CREATE_USER', "Tambah user baru: {$user->name} ({$user->role})", User::class, $user->id);

        return redirect()->back()->with('success', 'User berhasil ditambahkan.');
    }

    public function updateUser(Request $request, User $user)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username,' . $user->id,
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,apoteker,kasir',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $old = $user->toArray();
        $user->update([
            'name' => $v['name'],
            'username' => $v['username'],
            'email' => $v['email'],
            'role' => $v['role'],
        ]);

        if (!empty($v['password'])) {
            $user->update(['password' => \Illuminate\Support\Facades\Hash::make($v['password'])]);
        }

        AuditTrail::log($request->user()->id, 'UPDATE_USER', "Update user: {$user->name}", User::class, $user->id, $old, $v);

        return redirect()->back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroyUser(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return redirect()->back()->with('error', 'Anda tidak bisa menghapus diri sendiri.');
        }

        $name = $user->name;
        $user->delete();

        AuditTrail::log($request->user()->id, 'DELETE_USER', "Hapus user: {$name}");

        return redirect()->back()->with('success', 'User berhasil dihapus.');
    }
    // ─── ADMIN STOCK OPNAME ─────────────────────────────────
    public function stockOpnameIndex()
    {
        $opnames = \App\Models\Control\StockOpname::with('creator')
            ->whereHas('creator', function ($query) {
                $query->where('role', 'admin');
            })
            ->latest()
            ->paginate(15);

        $medicines = Medicine::where('status', 'active')->get();
        $rawMaterials = RawMaterial::where('status', 'active')->get();

        return Inertia::render('Control/Admin/StockOpname', [
            'opnames' => $opnames,
            'medicines' => $medicines,
            'rawMaterials' => $rawMaterials,
        ]);
    }

    public function stockOpnameStore(Request $request)
    {
        $v = $request->validate([
            'tanggal_opname' => 'required|date',
            'keterangan' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'nullable|exists:medicines,id',
            'items.*.raw_material_id' => 'nullable|exists:raw_materials,id',
            'items.*.qty_fisik' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($v, $request) {
            $opname = \App\Models\Control\StockOpname::create([
                'created_by' => $request->user()->id,
                'opname_date' => $v['tanggal_opname'],
                'catatan' => $v['keterangan'],
                'is_blind_count' => false,
                'status' => 'draft',
            ]);

            foreach ($v['items'] as $item) {
                $systemStock = 0;

                if (!empty($item['medicine_id'])) {
                    $systemStock = WarehouseInventory::where('medicine_id', $item['medicine_id'])
                        ->where('status', 'active')
                        ->sum('stock_on_hand');
                } elseif (!empty($item['raw_material_id'])) {
                    $systemStock = RawMaterialStock::where('raw_material_id', $item['raw_material_id'])
                        ->where('status', 'active')
                        ->sum('stock_on_hand');
                }

                $qtyFisik = $item['qty_fisik'];
                $difference = $qtyFisik - $systemStock;

                \App\Models\Control\StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'medicine_id' => $item['medicine_id'] ?? null,
                    'raw_material_id' => $item['raw_material_id'] ?? null,
                    'batch_no' => 'ALL_BATCHES',
                    'system_stock' => $systemStock,
                    'physical_stock' => $qtyFisik,
                    'difference' => $difference,
                    'reason' => $difference != 0 ? 'Admin Adjustment' : null,
                ]);
            }

            AuditTrail::log($request->user()->id, 'CREATE_ADMIN_OPNAME', "Admin membuat stock opname baru", \App\Models\Control\StockOpname::class, $opname->id);

            return redirect()->back()->with('success', 'Stock Opname berhasil disimpan.');
        });
    }

    public function stockOpnameShow($id)
    {
        $opname = \App\Models\Control\StockOpname::with([
            'creator',
            'finalizer',
            'items.medicine',
            'items.rawMaterial'
        ])->findOrFail($id);

        return Inertia::render('Control/Admin/StockOpnameDetail', [
            'opname' => $opname,
        ]);
    }

    public function stockOpnameAdjust($id, Request $request)
    {
        $opname = StockOpname::with('items')->findOrFail($id);

        if ($opname->status !== 'draft') {
            return redirect()->back()->with('error', 'Opname ini sudah difinalisasi.');
        }

        return DB::transaction(function () use ($opname, $request) {
            foreach ($opname->items as $item) {
                if ($item->difference != 0) {
                    if ($item->medicine_id) {
                        $wi = WarehouseInventory::where('medicine_id', $item->medicine_id)
                            ->where('status', 'active')
                            ->orderBy('expired_date', 'asc')
                            ->first();

                        if ($wi) {
                            $wi->stock_on_hand += $item->difference;
                            if ($wi->stock_on_hand < 0) {
                                $wi->stock_on_hand = 0;
                            }
                            $wi->save();

                            $wi->medicine->recalculateStock();
                        } else {
                            if ($item->physical_stock > 0) {
                                $wi = WarehouseInventory::create([
                                    'medicine_id' => $item->medicine_id,
                                    'batch_no' => 'ADJ-' . now()->format('Ymd'),
                                    'stock_on_hand' => $item->physical_stock,
                                    'status' => 'active',
                                ]);
                                $wi->medicine->recalculateStock();
                            }
                        }
                    } elseif ($item->raw_material_id) {
                        $rs = RawMaterialStock::where('raw_material_id', $item->raw_material_id)
                            ->where('status', 'active')
                            ->orderBy('expired_date', 'asc')
                            ->first();

                        if ($rs) {
                            $rs->stock_on_hand += $item->difference;
                            if ($rs->stock_on_hand < 0) {
                                $rs->stock_on_hand = 0;
                            }
                            $rs->save();

                            $rs->rawMaterial->update([
                                'stok_total' => RawMaterialStock::where('raw_material_id', $item->raw_material_id)
                                    ->where('status', 'active')
                                    ->sum('stock_on_hand')
                            ]);
                        } else {
                            if ($item->physical_stock > 0) {
                                $rs = RawMaterialStock::create([
                                    'raw_material_id' => $item->raw_material_id,
                                    'batch_no' => 'ADJ-' . now()->format('Ymd'),
                                    'stock_on_hand' => $item->physical_stock,
                                    'status' => 'active',
                                ]);
                                $rs->rawMaterial->update([
                                    'stok_total' => $item->physical_stock
                                ]);
                            }
                        }
                    }
                }
            }

            $opname->update([
                'status' => 'completed',
                'finalized_at' => now(),
                'finalized_by' => $request->user()->id,
            ]);

            AuditTrail::log($request->user()->id, 'FINALIZE_ADMIN_OPNAME', "Admin memfinalisasi stock opname", StockOpname::class, $opname->id);

            return redirect()->back()->with('success', 'Stock Opname berhasil difinalisasi dan stok telah disesuaikan.');
        });
    }

    // ─── PRICING ENGINE ─────────────────────────────────────
    public function pricingIndex()
    {
        $pendingProducts = CompoundedProduct::with('submission')
            ->where('pricing_status', 'pending_price')
            ->latest()
            ->get()
            ->map(function ($product) {
                $hpp = $product->hpp_per_unit;
                if ($hpp <= 0 && $product->submission) {
                    $qty = $product->qty_hasil > 0 ? $product->qty_hasil : ($product->submission->target_quantity > 0 ? $product->submission->target_quantity : 1);
                    $hpp = $product->submission->total_hpp_racikan / $qty;
                }
                $product->calculated_hpp = $hpp;
                return $product;
            });

        $medicines = Medicine::with([
            'incomingStocks' => function ($query) {
                $query->latest();
            }
        ])->latest()->get()->map(function ($med) {
            $latestStock = $med->incomingStocks->first();
            $hppKecil = $latestStock ? (float) $latestStock->calculateHppSatuan() : 0;
            $hppMenengah = 0;
            $hppBesar = 0;

            if ($latestStock) {
                // Strictly follow latest faktur
                $med->satuan_besar = $latestStock->satuan_input;
                $med->satuan_menengah = $latestStock->mid_unit;
                $med->mid_to_big_ratio = $latestStock->mid_to_big_ratio;
                $med->small_to_mid_ratio = $latestStock->small_to_mid_ratio;
                $med->latest_batch_no = $latestStock->batch_no;

                $smallToMid = (float) ($latestStock->small_to_mid_ratio ?: 1);
                $hppMenengah = $hppKecil * $smallToMid;

                if ($latestStock->mid_unit) {
                    $midToBig = (float) ($latestStock->mid_to_big_ratio ?: 1);
                    $hppBesar = $hppMenengah * $midToBig;
                } else {
                    $conversionFactor = (float) ($latestStock->unit_conversion_factor ?: 1);
                    $hppBesar = $hppKecil * $conversionFactor;
                }

                // Fix for 2-layer disguise (where mid_unit is identical to base_unit with ratio 1)
                if (strtolower($med->satuan_menengah) === strtolower($med->satuan_stok) && $med->small_to_mid_ratio == 1) {
                    $med->satuan_menengah = null;
                    $med->small_to_mid_ratio = $med->mid_to_big_ratio; // Conversion from Besar directly to Kecil(Base)
                    $med->mid_to_big_ratio = null;
                }
            }
            $med->base_unit = $med->satuan_stok;
            $med->hpp_kecil = $hppKecil;
            $med->hpp_menengah = $hppMenengah;
            $med->hpp_besar = $hppBesar;

            return $med;
        });

        return Inertia::render('Control/Admin/Pricing', [
            'pendingProducts' => $pendingProducts,
            'medicines' => $medicines,
        ]);
    }

    public function setPriceCompounded(Request $request, CompoundedProduct $product)
    {
        $v = $request->validate([
            'biaya_kemasan' => 'required|numeric|min:0',
            'jasa_racik' => 'required|numeric|min:0',
            'margin_percentage' => 'required|numeric|min:0',
            'total_modal_buat' => 'required|numeric|min:0',
            'harga_jual_final' => 'required|numeric|min:1',
            'admin_note' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($product, $v, $request) {
            $product->update([
                'harga_jual' => $v['harga_jual_final'],
                'pricing_status' => 'priced',
                'status' => 'active',
            ]);

            if ($product->submission) {
                $product->submission->update([
                    'status' => 'active',
                    'biaya_tuslah' => $v['jasa_racik'] + $v['biaya_kemasan'],
                    'total_hpp_bahan' => max(0, $v['total_modal_buat'] - ($v['jasa_racik'] + $v['biaya_kemasan'])),
                    'total_hpp_racikan' => $v['total_modal_buat'],
                    'harga_jual_racikan' => $v['harga_jual_final'],
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                    'admin_note' => $v['admin_note'] ?? null,
                ]);
            }

            AuditTrail::log(
                $request->user()->id,
                'PRICE_SET',
                "Penetapan harga racikan: {$product->nama_produk} (Modal: Rp " . number_format($v['total_modal_buat']) . " -> Jual: Rp " . number_format($v['harga_jual_final']) . ")",
                CompoundedProduct::class,
                $product->id
            );

            return redirect()->back()->with('success', 'Harga jual produk racikan berhasil ditetapkan dan siap dijual.');
        });
    }

    public function setPriceMedicine(SetMedicinePriceRequest $request, Medicine $medicine)
    {
        $v = $request->validated();

        return DB::transaction(function () use ($medicine, $v, $request) {
            $medicine->update([
                'selling_price_large' => $v['selling_price_large'] ?? null,
                'selling_price_mid' => $v['selling_price_mid'] ?? null,
                'selling_price' => $v['selling_price'] ?? null,
            ]);

            AuditTrail::log($request->user()->id, 'PRICE_SET', "Penetapan harga obat jadi: {$medicine->nama_obat}", Medicine::class, $medicine->id);

            return redirect()->back()->with('success', 'Harga jual obat jadi berhasil ditetapkan.');
        });
    }

    // ─── INVENTORY MIGRATED METHODS ─────────────────────────────


    public function inventory(Request $request)
    {
        $type = $request->query('type', 'obat'); // obat, bahan_baku, racikan
        $search = $request->search;

        if ($type === 'bahan_baku') {
            $query = RawMaterialStock::with('rawMaterial')
                ->where('status', 'active')
                ->where('stock_on_hand', '>', 0);

            $query->whereIn('id', function ($q) {
                $q->selectRaw('MIN(id)')->from('raw_material_stocks')->groupBy('raw_material_id', 'batch_no');
            });

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('rawMaterial', function ($mq) use ($search) {
                        $mq->where('nama_bahan', 'like', "%{$search}%");
                    })->orWhere('batch_no', 'like', "%{$search}%");
                });
            }

            $inventories = $query->orderBy('expired_date')->paginate(20)->withQueryString();
        } elseif ($type === 'racikan') {
            $query = CompoundedProduct::with(['submission.user'])
                ->where('status', 'active')
                ->where('qty_hasil', '>', 0);

            $query->whereIn('id', function ($q) {
                $q->selectRaw('MIN(id)')->from('compounded_products')->groupBy('nama_produk', 'batch_no');
            });

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_produk', 'like', "%{$search}%")
                        ->orWhere('batch_no', 'like', "%{$search}%");
                });
            }

            $inventories = $query->orderBy('bud_date')->paginate(20)->withQueryString();
        } else {
            // default is 'obat'
            $query = WarehouseInventory::with('medicine.category')
                ->where('status', 'active')
                ->where('stock_on_hand', '>', 0);

            $query->whereIn('id', function ($q) {
                $q->selectRaw('MIN(id)')->from('warehouse_inventories')->groupBy('medicine_id', 'batch_no');
            });

            if ($request->has('expired_within')) {
                $query->expiringSoon((int) $request->expired_within);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('medicine', function ($mq) use ($search) {
                        $q2 = $mq->where('nama_obat', 'like', "%{$search}%")->orWhere('kode_obat', 'like', "%{$search}%");
                    })->orWhere('batch_no', 'like', "%{$search}%");
                });
            }

            $inventories = $query->orderBy('expired_date')->paginate(20)->withQueryString();
        }

        $frozenMode = StockOpname::where('status', 'draft')->exists();

        return Inertia::render('Control/Admin/Inventory', [
            'inventories' => $inventories,
            'currentType' => $type,
            'frozenMode' => $frozenMode,
            'filters' => $request->only(['search', 'expired_within', 'type'])
        ]);
    }

    public function mutations(Request $request)
    {
        $query = \App\Models\Control\StockMutation::with(['inventory.medicine', 'rawMaterialStock.rawMaterial', 'item']);

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('inventory.medicine', function ($q) use ($search) {
                $q->where('nama_obat', 'like', "%{$search}%");
            });
        }

        $mutations = $query->latest()->paginate(25)->withQueryString();

        return Inertia::render('Control/Admin/MutationLog', [
            'mutations' => $mutations,
            'filters' => $request->only(['search', 'start_date', 'end_date'])
        ]);
    }

    public function returns()
    {
        $returns = \App\Models\Control\ReturnForm::with(['incomingStock.medicine', 'supplier', 'creator'])
            ->latest()
            ->paginate(20);

        // Fetch active incoming stocks that have available qty_converted > 0
        $activeIncomingStocks = \App\Models\Control\IncomingStock::with(['medicine', 'supplier'])
            ->where('qty_converted', '>', 0)
            ->whereIn('status_gate', ['pending_admin_verify', 'pending_admin_final', 'active'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Control/Admin/Returns', [
            'returns' => $returns,
            'activeIncomingStocks' => $activeIncomingStocks,
        ]);
    }

    public function storeReturn(Request $request)
    {
        $v = $request->validate([
            'incoming_stock_id' => 'required|exists:incoming_stocks,id',
            'qty_retur' => 'required|integer|min:1',
            'alasan_retur' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($v, $request) {
            $incomingStock = \App\Models\Control\IncomingStock::with('medicine')->findOrFail($v['incoming_stock_id']);

            if ($incomingStock->qty_converted < $v['qty_retur']) {
                return redirect()->back()->with('error', 'Kuantitas retur melebihi sisa stok di faktur.');
            }

            // Create Return Form
            $returnForm = \App\Models\Control\ReturnForm::create([
                'incoming_stock_id' => $incomingStock->id,
                'supplier_id' => $incomingStock->supplier_id,
                'qty_retur' => $v['qty_retur'],
                'alasan_retur' => $v['alasan_retur'],
                'status' => 'sent', // Langsung dikirim berdasarkan existing status flow
                'created_by' => $request->user()->id,

            ]);

            // Deduct Incoming Stock quantities (Katalog Faktur)
            $qty_terkecil = $v['qty_retur'];
            $incomingStock->qty_converted -= $qty_terkecil;
            if ($incomingStock->qty_fisik_verify !== null && $incomingStock->qty_fisik_verify >= $qty_terkecil) {
                $incomingStock->qty_fisik_verify -= $qty_terkecil;
            }
            $incomingStock->save();

            // Deduct Warehouse Inventory (Kasir sync)
            $wi = \App\Models\Control\WarehouseInventory::where('incoming_stock_id', $incomingStock->id)
                ->gudang()
                ->where('stock_on_hand', '>', 0)
                ->first();

            if ($wi) {
                $deductAmount = min($wi->stock_on_hand, $qty_terkecil);
                $wi->setMutationContext('out', $returnForm, 'Retur ke Supplier: ' . $v['alasan_retur']);
                $wi->update([
                    'stock_on_hand' => $wi->stock_on_hand - $deductAmount,
                ]);
            }

            // Recalculate medicine total
            if ($incomingStock->medicine) {
                $incomingStock->medicine->recalculateStock();
            }

            \App\Models\Control\AuditTrail::log(
                $request->user()->id,
                'RETURN_SUPPLIER',
                "Retur obat " . ($incomingStock->medicine ? $incomingStock->medicine->nama_obat : 'Unknown') . " sejumlah {$qty_terkecil} ke supplier",
                \App\Models\Control\ReturnForm::class,
                $returnForm->id
            );

            return redirect()->back()->with('success', 'Retur ke supplier berhasil diproses dan stok telah disesuaikan.');
        });
    }

    public function updateLocation(Request $request, WarehouseInventory $inventory)
    {
        $v = $request->validate([
            'shelf_location' => 'nullable|string|max:100',
            'rack_location' => 'nullable|string|max:100',
        ]);

        $inventory->update($v);

        AuditTrail::log(
            $request->user()->id,
            'UPDATE_LOCATION',
            "Ubah lokasi: {$inventory->medicine->nama_obat} batch {$inventory->batch_no} (Rak: {$inventory->rack_location}, Lemari: {$inventory->shelf_location})",
            WarehouseInventory::class,
            $inventory->id
        );

        return redirect()->back()->with('success', 'Lokasi inventaris diperbarui.');
    }

    public function requestInventoryAction(Request $request, WarehouseInventory $inventory)
    {
        $v = $request->validate([
            'action' => 'required|in:return,destruction',
            'note' => 'required|string|max:500',
        ]);

        $status = $v['action'] === 'return' ? 'pending_return' : 'pending_destruction';
        $inventory->update(['status' => $status]);

        $inventory->setMutationContext($v['action'], null, $v['note'])->save();

        AuditTrail::log(
            $request->user()->id,
            'REQUEST_INVENTORY_ACTION',
            "Pengajuan {$v['action']}: {$inventory->medicine->nama_obat} batch {$inventory->batch_no} - Alasan: {$v['note']}",
            WarehouseInventory::class,
            $inventory->id
        );

        return redirect()->back()->with('success', 'Pengajuan berhasil dikirim. Stok telah dikunci.');
    }

    public function distribution(Request $request)
    {
        $kasirs = \App\Models\User::where('role', 'kasir')->get();
        $selectedKasirId = $request->query('kasir_id');
        if (!$selectedKasirId && $kasirs->isNotEmpty()) {
            $selectedKasirId = $kasirs->first()->id;
        }
        $search = $request->query('search', '');
        
        $medicines = collect();
        if ($selectedKasirId) {
            $query = \App\Models\Control\Medicine::with([
                'category',
                'incomingStocks' => function($q) {
                    $q->orderBy('created_at', 'desc');
                },
                'warehouseInventories' => function($q) use ($selectedKasirId) {
                    $q->where('stock_on_hand', '>', 0)
                      ->where(function($sq) use ($selectedKasirId) {
                          $sq->where('location_type', 'gudang')
                             ->orWhere(function($ssq) use ($selectedKasirId) {
                                 $ssq->where('location_type', 'kasir')->where('location_id', $selectedKasirId);
                             });
                      })
                      ->orderBy('expired_date', 'asc');
                }
            ])->whereHas('warehouseInventories', function($q) use ($selectedKasirId) {
                $q->where('stock_on_hand', '>', 0)
                  ->where(function($sq) use ($selectedKasirId) {
                      $sq->where('location_type', 'gudang')
                         ->orWhere(function($ssq) use ($selectedKasirId) {
                             $ssq->where('location_type', 'kasir')->where('location_id', $selectedKasirId);
                         });
                  });
            });
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nama_obat', 'like', "%{$search}%")
                      ->orWhere('kode_obat', 'like', "%{$search}%");
                });
            }
            
            $medicinesData = $query->paginate(12)->withQueryString();
            
            // Map the medicines to include aggregate totals, base unit, and packaging info
            $medicinesData->getCollection()->transform(function ($medicine) {
                $gudangStock = (float) $medicine->warehouseInventories->where('location_type', 'gudang')->sum('stock_on_hand');
                $kasirStock = (float) $medicine->warehouseInventories->where('location_type', 'kasir')->sum('stock_on_hand');
                
                $satuanTerkecil = $medicine->satuan_stok ?: ($medicine->base_unit ?: 'Pcs');
                $medicine->stok_gudang = $gudangStock;
                $medicine->stok_kasir = $kasirStock;
                $medicine->satuan_terkecil = $satuanTerkecil;
                
                // Ambil data kemasan dari incoming_stock terakhir atau master obat
                $latestIncoming = $medicine->incomingStocks->first();
                $kemasan = $satuanTerkecil;
                if ($latestIncoming) {
                    if ($latestIncoming->satuan_input && $latestIncoming->mid_unit && $latestIncoming->small_to_mid_ratio > 0) {
                        $kemasan = "1 {$latestIncoming->satuan_input} = {$latestIncoming->mid_to_big_ratio} {$latestIncoming->mid_unit} × {$latestIncoming->small_to_mid_ratio} {$satuanTerkecil}";
                    } elseif ($latestIncoming->satuan_input && $latestIncoming->unit_conversion_factor > 1) {
                        $kemasan = "1 {$latestIncoming->satuan_input} = {$latestIncoming->unit_conversion_factor} {$satuanTerkecil}";
                    } elseif ($latestIncoming->satuan_input && strtolower($latestIncoming->satuan_input) !== strtolower($satuanTerkecil)) {
                        $kemasan = "{$latestIncoming->satuan_input} / {$satuanTerkecil}";
                    }
                } elseif ($medicine->satuan_besar && $medicine->rasio_konversi > 1) {
                    $kemasan = "1 {$medicine->satuan_besar} = {$medicine->rasio_konversi} {$satuanTerkecil}";
                }
                $medicine->kemasan = $kemasan;
                
                return $medicine;
            });
            
            $medicines = $medicinesData;
        }

        return Inertia::render('Control/Admin/Distribution', [
            'kasirs' => $kasirs,
            'selectedKasirId' => $selectedKasirId,
            'medicines' => $medicines,
            'filters' => ['search' => $search]
        ]);
    }

    public function processDistribution(Request $request)
    {
        $v = $request->validate([
            'kasir_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.action' => 'required|in:transfer,return',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);
        
        return DB::transaction(function () use ($v, $request) {
            $kasirId = $v['kasir_id'];
            
            foreach ($v['items'] as $item) {
                $medicineId = $item['medicine_id'];
                $qtyNeeded = floatval($item['quantity']);
                $action = $item['action'];
                
                if ($action === 'transfer') {
                    // Transfer: Gudang -> Kasir
                    // 1. Ambil stok Gudang dengan FEFO
                    $gudangInventories = \App\Models\Control\WarehouseInventory::where('medicine_id', $medicineId)
                        ->gudang()
                        ->where('stock_on_hand', '>', 0)
                        ->orderBy('expired_date', 'asc')
                        ->lockForUpdate()
                        ->get();
                        
                    $totalAvailable = $gudangInventories->sum('stock_on_hand');
                    if ($totalAvailable < $qtyNeeded) {
                        throw new \Exception("Stok gudang tidak mencukupi untuk obat ID {$medicineId}.");
                    }
                    
                    foreach ($gudangInventories as $sourceInv) {
                        if ($qtyNeeded <= 0) break;
                        
                        $deductAmount = min($sourceInv->stock_on_hand, $qtyNeeded);
                        
                        // Deduct from Gudang
                        $sourceInv->setMutationContext('internal_transfer', null, "Distribusi ke Kasir ID: {$kasirId}");
                        $sourceInv->stock_on_hand -= $deductAmount;
                        $sourceInv->save();
                        
                        // Add to Kasir
                        $destInv = \App\Models\Control\WarehouseInventory::firstOrNew([
                            'incoming_stock_id' => $sourceInv->incoming_stock_id,
                            'medicine_id' => $sourceInv->medicine_id,
                            'batch_no' => $sourceInv->batch_no,
                            'expired_date' => $sourceInv->expired_date,
                            'location_type' => 'kasir',
                            'location_id' => $kasirId
                        ]);
                        
                        $destInv->stock_on_hand += $deductAmount;
                        $destInv->status = $sourceInv->status;
                        $destInv->is_frozen = $sourceInv->is_frozen;
                        $destInv->setMutationContext('internal_transfer', null, "Terima Distribusi dari Gudang (Admin: {$request->user()->id})");
                        $destInv->save();
                        
                        $qtyNeeded -= $deductAmount;
                    }
                    
                } else {
                    // Return: Kasir -> Gudang
                    // 1. Ambil stok Kasir dengan FEFO
                    $kasirInventories = \App\Models\Control\WarehouseInventory::where('medicine_id', $medicineId)
                        ->kasir($kasirId)
                        ->where('stock_on_hand', '>', 0)
                        ->orderBy('expired_date', 'asc')
                        ->lockForUpdate()
                        ->get();
                        
                    $totalAvailable = $kasirInventories->sum('stock_on_hand');
                    if ($totalAvailable < $qtyNeeded) {
                        throw new \Exception("Stok kasir tidak mencukupi untuk obat ID {$medicineId}.");
                    }
                    
                    foreach ($kasirInventories as $sourceInv) {
                        if ($qtyNeeded <= 0) break;
                        
                        $deductAmount = min($sourceInv->stock_on_hand, $qtyNeeded);
                        
                        // Deduct from Kasir
                        $sourceInv->setMutationContext('internal_transfer', null, "Return dari Kasir ID: {$kasirId} ke Gudang");
                        $sourceInv->stock_on_hand -= $deductAmount;
                        $sourceInv->save();
                        
                        // Add to Gudang
                        $destInv = \App\Models\Control\WarehouseInventory::firstOrNew([
                            'incoming_stock_id' => $sourceInv->incoming_stock_id,
                            'medicine_id' => $sourceInv->medicine_id,
                            'batch_no' => $sourceInv->batch_no,
                            'expired_date' => $sourceInv->expired_date,
                            'location_type' => 'gudang',
                            'location_id' => null
                        ]);
                        
                        $destInv->stock_on_hand += $deductAmount;
                        $destInv->status = $sourceInv->status;
                        $destInv->is_frozen = $sourceInv->is_frozen;
                        $destInv->setMutationContext('internal_transfer', null, "Return Distribusi dari Kasir ID: {$kasirId}");
                        $destInv->save();
                        
                        $qtyNeeded -= $deductAmount;
                    }
                }
            }
            
            return redirect()->back()->with('success', 'Semua distribusi stok berhasil diproses.');
        });
    }
}
