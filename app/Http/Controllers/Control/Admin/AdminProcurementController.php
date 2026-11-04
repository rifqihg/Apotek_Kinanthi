<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\Setting;
use App\Models\Control\Supplier;
use App\Models\Control\WarehouseInventory;
use App\Services\IncomingStockService;
use App\Services\SatuSehatKfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminProcurementController extends Controller
{
    // ─── SUPPLIERS ──────────────────────────────────────────
    public function suppliers(Request $request)
    {
        $search = $request->query('search');

        $query = Supplier::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pbf', 'like', "%{$search}%")
                  ->orWhere('alamat', 'like', "%{$search}%")
                  ->orWhere('telepon', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Control/Admin/Suppliers', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search
            ]
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

    // ─── INCOMING STOCKS (FAKTUR) ───────────────────────────
    public function incomingStocks(Request $request)
    {
        $query = IncomingStock::with([
            'supplier:id,nama_pbf',
            'medicine:id,nama_obat,satuan_stok,rasio_konversi',
            'returnForm:id,incoming_stock_id',
            'warehouseInventories:id,incoming_stock_id,medicine_id,batch_no,stock_on_hand,location_type',
        ]);

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('faktur_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($q2) use ($search) {
                        $q2->where('nama_pbf', 'like', "%{$search}%");
                    })
                    ->orWhereHas('medicine', function ($q2) use ($search) {
                        $q2->where('nama_obat', 'like', "%{$search}%");
                    });
            });
        }

        $stocks = $query->latest()->paginate(15)->withQueryString();

        $settings = Setting::current();
        $suppliers = Supplier::active()->select(['id', 'nama_pbf'])->orderBy('nama_pbf')->get();

        return Inertia::render('Control/Admin/IncomingStocks', [
            'stocks' => $stocks,
            'settings' => $settings,
            'suppliers' => $suppliers,
            'medicines' => fn () => $this->getMedicinesCatalog(),
            'past_invoices' => fn () => $this->getPastInvoicesData(),
            'filters' => $request->only('search'),
        ]);
    }

    protected function getMedicinesCatalog(?string $search = null)
    {
        return Medicine::select(['id', 'nama_obat', 'satuan_stok', 'rasio_konversi', 'threshold_min', 'satuan_besar', 'satuan_menengah', 'kategori'])
            ->with(['latestIncomingStock' => function ($q) {
                $q->select([
                    'incoming_stocks.id', 'incoming_stocks.medicine_id', 'incoming_stocks.satuan_input', 'incoming_stocks.mid_unit',
                    'incoming_stocks.mid_to_big_ratio', 'incoming_stocks.small_to_mid_ratio', 'incoming_stocks.unit_conversion_factor',
                    'incoming_stocks.harga_beli_satuan', 'incoming_stocks.diskon_persen', 'incoming_stocks.harga_jual_akhir'
                ]);
            }])
            ->when($search, function ($q) use ($search) {
                $q->where('nama_obat', 'like', "%{$search}%");
            })
            ->orderBy('nama_obat')
            ->limit($search ? 30 : 60)
            ->get()
            ->map(function ($m) {
                $latest = $m->latestIncomingStock;
                $m->current_price = (float) ($latest?->harga_jual_akhir ?? 0);
                $m->latest_harga_beli = (float) ($latest?->harga_beli_satuan ?? 0);
                $m->latest_diskon = (float) ($latest?->diskon_persen ?? 0);
                $m->latest_satuan_input = $latest?->satuan_input ?? $m->satuan_besar;
                $m->latest_mid_unit = $latest?->mid_unit ?? $m->satuan_menengah;
                $m->latest_mid_to_big = (int) ($latest?->mid_to_big_ratio ?? 1);
                $m->latest_small_to_mid = (int) ($latest?->small_to_mid_ratio ?? ($m->rasio_konversi ?: 1));
                $m->latest_unit_conversion = (int) ($latest?->unit_conversion_factor ?? ($m->rasio_konversi ?: 1));
                unset($m->latestIncomingStock);
                return $m;
            });
    }

    public function searchMedicines(Request $request)
    {
        return response()->json([
            'medicines' => $this->getMedicinesCatalog($request->query('query')),
        ]);
    }

    public function satusehatLookup(Request $request, SatuSehatKfaService $service)
    {
        $query = (string) ($request->query('q') ?? $request->query('barcode') ?? $request->query('nie') ?? $request->query('kfa') ?? '');
        $type = $request->query('type'); // 'name' | 'nie' | 'kfa' | 'barcode' | null

        if (trim($query) === '') {
            return response()->json([
                'found' => false,
                'message' => 'Parameter pencarian wajib diisi.',
            ], 422);
        }

        if ($type === 'name') {
            $page = max(1, (int) $request->query('page', 1));
            $limit = max(1, min(25, (int) $request->query('limit', 10)));
            $result = $service->searchByName($query, $limit, $page);
            return response()->json($result);
        }

        $result = $service->lookup($query, $type);
        return response()->json($result);
    }

    protected function getPastInvoicesData(?int $supplierId = null, ?string $search = null): array
    {
        $query = IncomingStock::with(['supplier:id,nama_pbf', 'medicine:id,nama_obat,satuan_stok,threshold_min,kategori'])
            ->select([
                'id', 'faktur_no', 'supplier_id', 'medicine_id', 'tanggal_faktur',
                'satuan_input', 'mid_unit', 'mid_to_big_ratio', 'small_to_mid_ratio',
                'unit_conversion_factor', 'harga_beli_satuan', 'diskon_persen', 'qty_masuk',
                'created_at'
            ]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('faktur_no', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn($sq) => $sq->where('nama_pbf', 'like', "%{$search}%"))
                  ->orWhereHas('medicine', fn($mq) => $mq->where('nama_obat', 'like', "%{$search}%"));
            });
        }

        $stocks = $query->orderBy('tanggal_faktur', 'desc')->orderBy('id', 'desc')->limit(50)->get();
        $invoices = [];

        foreach ($stocks as $stock) {
            $fakturNo = $stock->faktur_no;
            if (!isset($invoices[$fakturNo])) {
                $invoices[$fakturNo] = [
                    'faktur_no' => $fakturNo,
                    'supplier_id' => $stock->supplier_id,
                    'supplier_name' => $stock->supplier?->nama_pbf ?? '-',
                    'tanggal_faktur' => $stock->tanggal_faktur ? \Carbon\Carbon::parse($stock->tanggal_faktur)->format('Y-m-d') : null,
                    'items_count' => 0,
                    'items' => [],
                ];
            }

            $invoices[$fakturNo]['items_count']++;
            $invoices[$fakturNo]['items'][] = [
                'medicine_id' => $stock->medicine_id,
                'medicine_name' => $stock->medicine?->nama_obat ?? '-',
                'satuan_stok' => $stock->medicine?->satuan_stok ?? 'Pcs',
                'min_stock_threshold' => $stock->medicine?->threshold_min ?? 10,
                'kategori' => $stock->medicine?->kategori ?? null,
                'satuan_input' => $stock->satuan_input ?: 'Box',
                'mid_unit' => $stock->mid_unit,
                'mid_to_big_ratio' => $stock->mid_to_big_ratio ?: 1,
                'small_to_mid_ratio' => $stock->small_to_mid_ratio ?: 1,
                'unit_conversion_factor' => $stock->unit_conversion_factor ?: 1,
                'harga_beli_satuan' => (float) $stock->harga_beli_satuan,
                'diskon_persen' => (float) $stock->diskon_persen,
                'qty_masuk' => (int) ($stock->qty_masuk ?: 1),
            ];
        }

        return array_values(array_slice($invoices, 0, 30));
    }

    public function pastInvoices(Request $request)
    {
        $supplierId = $request->query('supplier_id') ? (int) $request->query('supplier_id') : null;
        $search = $request->query('search');

        return response()->json([
            'invoices' => $this->getPastInvoicesData($supplierId, $search)
        ]);
    }

    public function storeIncomingStock(Request $request, IncomingStockService $service)
    {
        // Normalize items array for multipart/form-data requests (booleans as "false"/"true")
        $items = $request->input('items', []);
        if (is_array($items)) {
            foreach ($items as $i => $item) {
                if (isset($item['is_new_medicine'])) {
                    $items[$i]['is_new_medicine'] = filter_var($item['is_new_medicine'], FILTER_VALIDATE_BOOLEAN);
                } else {
                    $items[$i]['is_new_medicine'] = false;
                }
                if (empty($items[$i]['medicine_id'])) {
                    $items[$i]['medicine_id'] = null;
                }
                if (isset($item['no_multi_unit'])) {
                    $items[$i]['no_multi_unit'] = filter_var($item['no_multi_unit'], FILTER_VALIDATE_BOOLEAN);
                }
                if (isset($item['small_unit_skipped'])) {
                    $items[$i]['small_unit_skipped'] = filter_var($item['small_unit_skipped'], FILTER_VALIDATE_BOOLEAN);
                }
            }
            $request->merge(['items' => $items]);
        }

        $validated = $request->validate([
            'faktur_no' => 'required|string|max:100',
            'supplier_id' => 'required|exists:suppliers,id',
            'tanggal_faktur' => 'nullable|date',
            'foto_faktur' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'items' => 'required|array|min:1',
            'items.*.is_new_medicine' => 'boolean',
            'items.*.medicine_id' => 'required_if:items.*.is_new_medicine,false,0|nullable|exists:medicines,id',
            'items.*.new_medicine_name' => 'required_if:items.*.is_new_medicine,true,1|nullable|string|max:255',
            'items.*.new_medicine_kategori' => 'nullable|string|max:255',
            'items.*.new_medicine_satuan_besar' => 'nullable|string|max:50',
            'items.*.new_medicine_satuan_kecil' => 'required_if:items.*.is_new_medicine,true,1|nullable|string|max:50',
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
            'items.*.min_stock_threshold' => 'nullable|integer|min:0',
        ], [
            'items.*.medicine_id.required_if' => 'Silakan pilih obat dari katalog untuk setiap baris obat yang bukan obat baru.',
            'items.*.medicine_id.exists' => 'Obat yang dipilih tidak valid atau tidak terdaftar.',
            'items.*.new_medicine_name.required_if' => 'Nama obat baru wajib diisi jika dicentang sebagai obat baru.',
            'items.*.qty_masuk.required' => 'Jumlah (Qty) masuk wajib diisi.',
            'items.*.qty_masuk.min' => 'Jumlah (Qty) masuk minimal 1.',
            'items.*.harga_beli_satuan.required' => 'Harga beli satuan wajib diisi.',
        ]);

        $settings = Setting::current();
        $validated['ppn_persen'] = $settings->default_ppn ?? 11.00;

        $service->store($validated, $request->file('foto_faktur'), $request->user()->id);

        return redirect()->back()->with('success', 'Faktur berhasil disimpan dan stok langsung aktif.');
    }

    /**
     * Tampilkan file foto faktur secara aman dari disk privat (local/public fallback).
     */
    public function showFakturFile(IncomingStock $incomingStock)
    {
        if (!$incomingStock->foto_faktur_path) {
            abort(404, 'Faktur tidak ditemukan.');
        }

        $disk = Storage::disk('local')->exists($incomingStock->foto_faktur_path)
            ? 'local'
            : (Storage::disk('public')->exists($incomingStock->foto_faktur_path) ? 'public' : null);

        if (!$disk) {
            abort(404, 'File fisik faktur tidak ditemukan di server.');
        }

        $path = Storage::disk($disk)->path($incomingStock->foto_faktur_path);
        return response()->file($path);
    }

    /**
     * Smart Guarded Edit: Update Faktur Masuk (Metadata, Harga, Batch, dan Kuantitas Aman).
     */
    public function updateIncomingInvoice(Request $request)
    {
        $validated = $request->validate([
            'original_faktur_no'        => 'required|string',
            'faktur_no'                 => 'required|string|max:100',
            'supplier_id'               => 'required|exists:suppliers,id',
            'tanggal_faktur'            => 'required|date',
            'ppn_persen'                => 'required|numeric|min:0|max:100',
            'foto_faktur'               => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'items'                     => 'required|array|min:1',
            'items.*.id'                => 'required|exists:incoming_stocks,id',
            'items.*.is_new_medicine'   => 'nullable',
            'items.*.medicine_id'       => 'nullable|integer',
            'items.*.new_medicine_name' => 'nullable|string|max:255',
            'items.*.batch_no'          => 'required|string|max:100',
            'items.*.expired_date'      => 'required|date',
            'items.*.qty_masuk'         => 'required|integer|min:1',
            'items.*.harga_beli_satuan' => 'required|numeric|min:0',
            'items.*.diskon_persen'     => 'nullable|numeric|min:0|max:100',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $originalFakturNo = $validated['original_faktur_no'];
            $newFakturNo = trim($validated['faktur_no']);
            $supplierId = (int) $validated['supplier_id'];
            $tanggalFaktur = $validated['tanggal_faktur'];
            $ppnPersen = (float) $validated['ppn_persen'];

            $stocks = IncomingStock::with('medicine')->where('faktur_no', $originalFakturNo)->get();

            if ($stocks->isEmpty()) {
                return redirect()->back()->with('error', "Faktur {$originalFakturNo} tidak ditemukan.");
            }

            // Upload foto baru jika ada
            $newFotoPath = null;
            if ($request->hasFile('foto_faktur')) {
                $newFotoPath = $request->file('foto_faktur')->store('faktur', 'local');
            }

            $affectedMedicineIds = collect();

            foreach ($validated['items'] as $itemData) {
                $stock = $stocks->firstWhere('id', (int) $itemData['id']);
                if (!$stock) continue;

                $affectedMedicineIds->push($stock->medicine_id);

                $oldMedicineId = (int) $stock->medicine_id;
                $isNewMed = filter_var($itemData['is_new_medicine'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($isNewMed) {
                    $newMedName = trim($itemData['new_medicine_name'] ?? '');
                    if (!$newMedName) {
                        return redirect()->back()->with('error', 'Nama obat baru wajib diisi.');
                    }

                    $existing = Medicine::where('nama_obat', $newMedName)->first();
                    if ($existing) {
                        $newMedicineId = $existing->id;
                    } else {
                        $createdMed = Medicine::create([
                            'nama_obat'       => $newMedName,
                            'satuan_besar'    => $stock->satuan_input ?? 'Box',
                            'satuan_menengah' => $stock->mid_unit ?? null,
                            'satuan_stok'     => $stock->medicine?->satuan_stok ?? 'Strip',
                            'rasio_konversi'  => $stock->unit_conversion_factor ?? 1,
                            'threshold_min'   => 10,
                            'stok_total'      => 0,
                            'status'          => 'active',
                        ]);
                        $newMedicineId = $createdMed->id;

                        AuditTrail::log(
                            $request->user()->id,
                            'CREATE_MEDICINE',
                            "Menambah sediaan obat baru dari koreksi Faktur {$newFakturNo}: {$createdMed->nama_obat}",
                            Medicine::class,
                            $createdMed->id
                        );
                    }
                } else {
                    $newMedicineId = (int) ($itemData['medicine_id'] ?? $stock->medicine_id);
                }

                $oldBatch = $stock->batch_no;
                $newBatch = trim($itemData['batch_no']);
                $newExp = $itemData['expired_date'];
                $newHargaBeli = (float) $itemData['harga_beli_satuan'];
                $newDiskon = (float) ($itemData['diskon_persen'] ?? 0);
                $newQtyMasuk = (int) $itemData['qty_masuk'];

                // Ambil stok gudang terkait
                $wi = WarehouseInventory::where('incoming_stock_id', $stock->id)
                    ->where('location_type', 'gudang')
                    ->lockForUpdate()
                    ->first();

                if (!$wi) {
                    $wi = WarehouseInventory::where('medicine_id', $stock->medicine_id)
                        ->where('batch_no', $oldBatch)
                        ->where('location_type', 'gudang')
                        ->lockForUpdate()
                        ->first();
                }

                // Handle jika obat dialihkan ke obat lain (re-assign medicine)
                if ($newMedicineId !== $oldMedicineId) {
                    // Pastikan tidak ada stok yang sudah berada di kasir
                    $cashierStock = WarehouseInventory::where('incoming_stock_id', $stock->id)
                        ->where('location_type', 'kasir')
                        ->where('stock_on_hand', '>', 0)
                        ->exists();

                    if ($cashierStock) {
                        $medName = $stock->medicine?->nama_obat ?? 'Obat';
                        return redirect()->back()->with('error', "Obat {$medName} tidak dapat dialihkan karena sebagian stok sudah terdistribusi ke kasir atau terjual.");
                    }

                    if ($wi) {
                        $wi->medicine_id = $newMedicineId;
                        $wi->setMutationContext('adjustment', null, "Pengalihan obat faktur {$newFakturNo} dari ID {$oldMedicineId} ke ID {$newMedicineId}");
                        $wi->save();
                    }

                    $stock->medicine_id = $newMedicineId;
                    $affectedMedicineIds->push($newMedicineId);
                }

                // Hitung selisih kuantitas konversi
                $factor = (int) ($stock->unit_conversion_factor ?: 1);
                $oldQtyConverted = (int) $stock->qty_converted;
                $newQtyConverted = $newQtyMasuk * $factor;
                $deltaQty = $newQtyConverted - $oldQtyConverted;

                if ($deltaQty != 0) {
                    if ($wi) {
                        // Jika ingin mengurangi kuantitas, pastikan stok fisik di gudang cukup untuk dikurangi
                        if ($deltaQty < 0 && $wi->stock_on_hand < abs($deltaQty)) {
                            $medName = $stock->medicine?->nama_obat ?? 'Obat';
                            $unit = $stock->medicine?->satuan_stok ?? 'item';
                            return redirect()->back()->with('error', "Kuantitas {$medName} tidak dapat dikurangi. Sisa stok di gudang hanya {$wi->stock_on_hand} {$unit} karena sebagian stok telah terdistribusi ke kasir atau terjual.");
                        }

                        $wi->setMutationContext('adjustment', null, "Koreksi Qty Faktur {$newFakturNo}");
                        $wi->stock_on_hand += $deltaQty;
                    }
                    $stock->qty_masuk = $newQtyMasuk;
                    $stock->qty_converted = $newQtyConverted;
                    $stock->qty_fisik_verify = $newQtyConverted;
                }

                // Perbarui batch & exp di stok gudang
                if ($wi) {
                    $wi->batch_no = $newBatch;
                    $wi->expired_date = $newExp;
                    $wi->save();
                }

                // Update data incoming stock
                $stock->faktur_no = $newFakturNo;
                $stock->supplier_id = $supplierId;
                $stock->tanggal_faktur = $tanggalFaktur;
                $stock->ppn_persen = $ppnPersen;
                $stock->batch_no = $newBatch;
                $stock->expired_date = $newExp;
                $stock->harga_beli_satuan = $newHargaBeli;
                $stock->diskon_persen = $newDiskon;
                $stock->jumlah_harga = ($newHargaBeli * (1 - ($newDiskon / 100))) * $stock->qty_masuk;

                if ($newFotoPath) {
                    $stock->foto_faktur_path = $newFotoPath;
                }

                $stock->save();
            }

            // Jika nomor faktur atau foto berubah, sinkronkan seluruh item dalam faktur ini
            if ($originalFakturNo !== $newFakturNo || $newFotoPath) {
                IncomingStock::where('faktur_no', $originalFakturNo)->update([
                    'faktur_no' => $newFakturNo,
                    'supplier_id' => $supplierId,
                    'tanggal_faktur' => $tanggalFaktur,
                    'ppn_persen' => $ppnPersen,
                    ...($newFotoPath ? ['foto_faktur_path' => $newFotoPath] : [])
                ]);
            }

            // Recalculate stok master obat
            foreach ($affectedMedicineIds->unique() as $mid) {
                Medicine::find($mid)?->recalculateStock();
            }

            AuditTrail::log(
                $request->user()->id,
                'UPDATE_INCOMING_INVOICE',
                "Admin memperbarui data Faktur {$originalFakturNo}" . ($originalFakturNo !== $newFakturNo ? " menjadi {$newFakturNo}" : ""),
                IncomingStock::class,
                $stocks->first()?->id
            );

            return redirect()->back()->with('success', "Faktur {$newFakturNo} berhasil diperbarui.");
        });
    }
}
