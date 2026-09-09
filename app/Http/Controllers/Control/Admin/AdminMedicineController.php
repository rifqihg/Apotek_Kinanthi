<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Control\RawMaterialStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminMedicineController extends Controller
{
    // ─── MEDICINES (MASTER OBAT FAKTUR) ─────────────────────
    public function medicinesFaktur(Request $request)
    {
        $search = $request->input('search');

        // 1. Katalog Faktur (Data Faktur Masuk yang valid)
        $query = IncomingStock::with(['medicine', 'supplier'])
            ->withSum('warehouseInventories', 'stock_on_hand')
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
                $liveStock = (float) ($stock->warehouse_inventories_sum_stock_on_hand ?? 0);
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
                'batch_no' => $v['batch_no'] ?? 'BATCH-' . strtoupper(Str::random(5)),
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
            if ($medicine->foto_obat && Storage::disk('public')->exists('medicines/' . $medicine->foto_obat)) {
                Storage::disk('public')->delete('medicines/' . $medicine->foto_obat);
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
}
