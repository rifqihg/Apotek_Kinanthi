<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Control\StockLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApotekerInventoryController extends Controller
{
    /**
     * Check current physical stocks of raw materials and catalog medicines.
     */
    public function stockCheck(Request $request)
    {
        $search = $request->query('search');

        $rawMaterials = RawMaterial::select(['id', 'kode_bahan', 'nama_bahan', 'satuan'])
            ->with(['stocks' => fn($q) => $q->select(['id', 'raw_material_id', 'batch_no', 'expired_date', 'stock_on_hand', 'status', 'shelf_location', 'rack_location'])
                ->where('stock_on_hand', '>', 0)
                ->orderBy('expired_date')
            ])
            ->when($search, fn($q) => $q->where(fn($sub) => 
                $sub->where('nama_bahan', 'like', "%{$search}%")
                    ->orWhere('kode_bahan', 'like', "%{$search}%")
            ))
            ->orderBy('nama_bahan')
            ->limit(50)
            ->get();

        $medicines = Medicine::select(['id', 'kode_obat', 'nama_obat', 'satuan_stok', 'base_unit', 'selling_price', 'stok_total', 'category_id'])
            ->with(['warehouseInventories' => fn($q) => $q->select(['id', 'medicine_id', 'batch_no', 'expired_date', 'stock_on_hand', 'status', 'shelf_location', 'rack_location'])
                ->active()
                ->where('stock_on_hand', '>', 0)
                ->orderBy('expired_date')
            ])
            ->where('stok_total', '>', 0)
            ->when($search, fn($q) => $q->where(fn($sub) => 
                $sub->where('nama_obat', 'like', "%{$search}%")
                    ->orWhere('kode_obat', 'like', "%{$search}%")
            ))
            ->orderBy('nama_obat')
            ->limit(50)
            ->get();

        return Inertia::render('Control/Apoteker/StockCheck', [
            'rawMaterials' => $rawMaterials,
            'medicines'    => $medicines,
            'filters'      => [
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Display catalog of raw materials accessible by Apoteker.
     */
    public function rawMaterials()
    {
        return Inertia::render('Control/Apoteker/RawMaterials', [
            'rawMaterials' => RawMaterial::where('creator_id', auth()->id())->orWhere('status', 'active')->latest()->paginate(15),
        ]);
    }

    /**
     * Display audit trail log of stock movements for Apoteker.
     */
    public function auditLogs()
    {
        return Inertia::render('Control/Apoteker/StockAudit', [
            'logs' => StockLog::with(['user', 'rawMaterial', 'medicine'])->latest()->paginate(25),
        ]);
    }
}
