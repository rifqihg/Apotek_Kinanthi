<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Control\RawMaterialStock;
use App\Models\Control\StockOpname;
use App\Models\Control\StockOpnameItem;
use App\Models\Control\WarehouseInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminStockOpnameController extends Controller
{
    /**
     * Display a listing of stock opnames initiated by Admin.
     */
    public function stockOpnameIndex()
    {
        $opnames = StockOpname::with('creator')
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

    /**
     * Store a new stock opname session and record item counts & differences.
     */
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
            $opname = StockOpname::create([
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

                StockOpnameItem::create([
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

            AuditTrail::log($request->user()->id, 'CREATE_ADMIN_OPNAME', "Admin membuat stock opname baru", StockOpname::class, $opname->id);

            return redirect()->back()->with('success', 'Stock Opname berhasil disimpan.');
        });
    }

    /**
     * Display the details of a specific stock opname session.
     */
    public function stockOpnameShow($id)
    {
        $opname = StockOpname::with([
            'creator',
            'finalizer',
            'items.medicine',
            'items.rawMaterial'
        ])->findOrFail($id);

        return Inertia::render('Control/Admin/StockOpnameDetail', [
            'opname' => $opname,
        ]);
    }

    /**
     * Finalize the stock opname and execute inventory adjustments.
     */
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
}
