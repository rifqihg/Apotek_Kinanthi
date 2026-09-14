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
        return Inertia::render('Control/Admin/StockOpname', [
            'opnames' => StockOpname::with('creator')
                ->whereHas('creator', fn($q) => $q->where('role', 'admin'))
                ->latest()
                ->paginate(15),
            'medicines' => Medicine::where('status', 'active')
                ->select(['id', 'nama_obat'])
                ->orderBy('nama_obat')
                ->get(),
            'rawMaterials' => RawMaterial::where('status', 'active')
                ->select(['id', 'nama_bahan'])
                ->orderBy('nama_bahan')
                ->get(),
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
            $userId = (int) (auth()->id() ?? $request->user()?->id);
            $opname = StockOpname::create([
                'created_by' => $userId,
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

                $qtyFisik = (float) $item['qty_fisik'];
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

            AuditTrail::log($userId, 'CREATE_ADMIN_OPNAME', "Admin membuat stock opname baru", StockOpname::class, $opname->id);

            return redirect()->back()->with('success', 'Stock Opname berhasil disimpan.');
        });
    }

    /**
     * Display the details of a specific stock opname session.
     */
    public function stockOpnameShow($id)
    {
        return Inertia::render('Control/Admin/StockOpnameDetail', [
            'opname' => StockOpname::with([
                'creator',
                'finalizer',
                'items.medicine',
                'items.rawMaterial'
            ])->findOrFail($id),
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
                        $this->applyOpnameAdjustment(
                            WarehouseInventory::class,
                            'medicine_id',
                            (int) $item->medicine_id,
                            (float) $item->difference,
                            (float) $item->physical_stock,
                            $opname->id
                        );

                        if ($item->medicine) {
                            $item->medicine->recalculateStock();
                        }
                    } elseif ($item->raw_material_id) {
                        $this->applyOpnameAdjustment(
                            RawMaterialStock::class,
                            'raw_material_id',
                            (int) $item->raw_material_id,
                            (float) $item->difference,
                            (float) $item->physical_stock,
                            $opname->id
                        );

                        if ($item->rawMaterial) {
                            $item->rawMaterial->update([
                                'stok_total' => RawMaterialStock::where('raw_material_id', $item->raw_material_id)
                                    ->where('status', 'active')
                                    ->sum('stock_on_hand')
                            ]);
                        }
                    }
                }
            }

            $userId = (int) (auth()->id() ?? $request->user()?->id);
            $opname->update([
                'status' => 'completed',
                'finalized_at' => now(),
                'finalized_by' => $userId,
            ]);

            AuditTrail::log($userId, 'FINALIZE_ADMIN_OPNAME', "Admin memfinalisasi stock opname", StockOpname::class, $opname->id);

            return redirect()->back()->with('success', 'Stock Opname berhasil difinalisasi dan stok telah disesuaikan.');
        });
    }

    /**
     * Eksekusi penyesuaian stok opname secara polimorfik (FEFO chain deduction untuk minus, atau alokasi batch untuk surplus).
     */
    private function applyOpnameAdjustment(string $modelClass, string $foreignKey, int $foreignId, float $diff, float $physicalStock, int $opnameId): void
    {
        if ($diff < 0) {
            $remainingToDeduct = abs($diff);
            $batches = $modelClass::where($foreignKey, $foreignId)
                ->where('status', 'active')
                ->where('stock_on_hand', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remainingToDeduct <= 0) break;

                $currentStock = (float) $batch->stock_on_hand;
                $deductQty = min($currentStock, $remainingToDeduct);
                $remainingToDeduct -= $deductQty;

                $batch->stock_on_hand = $currentStock - $deductQty;
                $batch->mutationContext = [
                    'type' => 'opname_adjustment',
                    'reference_type' => StockOpname::class,
                    'reference_id' => $opnameId,
                    'note' => "Penyesuaian Opname: Selisih fisik minus (-{$deductQty})",
                ];
                $batch->save();
            }
        } else {
            $latestBatch = $modelClass::where($foreignKey, $foreignId)
                ->where('status', 'active')
                ->orderBy('expired_date', 'desc')
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            if ($latestBatch) {
                $latestBatch->stock_on_hand += $diff;
                $latestBatch->mutationContext = [
                    'type' => 'opname_adjustment',
                    'reference_type' => StockOpname::class,
                    'reference_id' => $opnameId,
                    'note' => "Penyesuaian Opname: Selisih fisik plus (+{$diff})",
                ];
                $latestBatch->save();
            } else {
                $newBatch = $modelClass::create([
                    $foreignKey => $foreignId,
                    'batch_no' => 'ADJ-' . now()->format('Ymd'),
                    'stock_on_hand' => $physicalStock,
                    'expired_date' => now()->addYear(),
                    'status' => 'active',
                ]);
                $newBatch->mutationContext = [
                    'type' => 'opname_adjustment',
                    'reference_type' => StockOpname::class,
                    'reference_id' => $opnameId,
                    'note' => "Penyesuaian Opname: Batch baru penyesuaian (+{$physicalStock})",
                ];
                $newBatch->save();
            }
        }
    }
}
