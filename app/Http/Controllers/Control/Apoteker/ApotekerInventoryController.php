<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Control\StockLog;
use Inertia\Inertia;

class ApotekerInventoryController extends Controller
{
    /**
     * Check current physical stocks of raw materials and catalog medicines.
     */
    public function stockCheck()
    {
        return Inertia::render('Control/Apoteker/StockCheck', [
            'rawMaterials' => RawMaterial::with(['stocks' => fn($q) => $q->where('stock_on_hand', '>', 0)->orderBy('expired_date')])->get(),
            'medicines' => Medicine::with(['warehouseInventories' => fn($q) => $q->active()->where('stock_on_hand', '>', 0)->orderBy('expired_date')])
                ->where('stok_total', '>', 0)
                ->orderBy('nama_obat')
                ->get(),
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
