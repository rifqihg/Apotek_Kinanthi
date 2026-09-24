<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\Setting;
use App\Models\Control\Supplier;
use App\Services\IncomingStockService;
use Illuminate\Http\Request;
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
        $query = IncomingStock::with(['supplier', 'medicine', 'returnForm']);

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

        $suppliers = Supplier::active()->select(['id', 'nama_pbf'])->orderBy('nama_pbf')->get();
        $medicines = Medicine::with('latestActiveIncomingStock')
            ->select(['id', 'nama_obat', 'satuan_stok', 'rasio_konversi', 'threshold_min', 'satuan_besar', 'satuan_menengah'])
            ->orderBy('nama_obat')
            ->get()
            ->map(function ($m) {
                $lastApproved = $m->latestActiveIncomingStock;
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
}
