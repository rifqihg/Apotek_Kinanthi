<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Control\WarehouseInventory;
use App\Models\Control\RawMaterialStock;

class CheckFrozenStock
{
    /**
     * Prevent operations on frozen stock.
     */
    public function handle(Request $request, Closure $next)
    {
        // For compounding submissions or sales that specify batches
        if ($request->has('batches')) {
            foreach ($request->batches as $batch) {
                $inventory = WarehouseInventory::find($batch['id']);
                if ($inventory && $inventory->is_frozen) {
                    return back()->with('error', "Obat [{$inventory->medicine->nama_obat}] sedang dalam proses audit gudang (Opname).");
                }
            }
        }

        // For raw material compounding
        if ($request->has('raw_materials')) {
            foreach ($request->raw_materials as $rm) {
                $stock = RawMaterialStock::find($rm['id']);
                if ($stock && $stock->is_frozen) {
                    return back()->with('error', "Bahan Baku [{$stock->rawMaterial->nama_bahan}] sedang dalam proses audit gudang (Opname).");
                }
            }
        }

        return $next($request);
    }
}
