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
use Inertia\Inertia;

class AdminProcurementController extends Controller
{
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
            'items.*.min_stock_threshold' => 'nullable|integer|min:0',
        ]);

        $settings = Setting::current();
        $validated['ppn_persen'] = $settings->default_ppn ?? 11.00;

        $service->store($validated, $request->file('foto_faktur'), $request->user()->id);

        return redirect()->back()->with('success', 'Faktur berhasil disimpan dan stok langsung aktif.');
    }
}
