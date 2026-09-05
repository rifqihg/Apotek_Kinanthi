<?php

namespace App\Services;

use App\Models\Control\AuditTrail;
use App\Models\Control\StockOpname;
use App\Models\Control\StockOpnameItem;
use App\Models\Control\WarehouseInventory;
use App\Models\Control\RawMaterialStock;
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Control\StockMutation;
use Illuminate\Support\Facades\DB;

class StockOpnameService
{
    /**
     * Create a new draft opname session, pre-populating current batches and freezing them.
     */
    public function createDraft(int $userId, bool $isBlindCount = false, ?int $categoryId = null): StockOpname
    {
        return DB::transaction(function () use ($userId, $isBlindCount, $categoryId) {
            $opname = StockOpname::create([
                'created_by' => $userId,
                'category_id' => $categoryId,
                'opname_date' => now()->toDateString(),
                'is_blind_count' => $isBlindCount,
                'status' => 'draft',
            ]);

            // 1. Process Medicines
            $medicinesQuery = WarehouseInventory::where('stock_on_hand', '>', 0)->where('status', 'active');
            if ($categoryId) {
                $medicinesQuery->whereHas('medicine', fn($q) => $q->where('category_id', $categoryId));
            }
            $batches = $medicinesQuery->get();

            foreach ($batches as $batch) {
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'medicine_id' => $batch->medicine_id,
                    'warehouse_inventory_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'system_stock' => $batch->stock_on_hand,
                ]);
                
                // Freeze stock
                $batch->update(['is_frozen' => true]);
            }

            // 2. Process Raw Materials
            $materialsQuery = RawMaterialStock::where('stock_on_hand', '>', 0)->where('status', 'active');
            if ($categoryId) {
                $materialsQuery->whereHas('rawMaterial', fn($q) => $q->where('category_id', $categoryId));
            }
            $rawBatches = $materialsQuery->get();

            foreach ($rawBatches as $batch) {
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'raw_material_id' => $batch->raw_material_id,
                    'raw_material_stock_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'system_stock' => $batch->stock_on_hand,
                ]);

                // Freeze stock
                $batch->update(['is_frozen' => true]);
            }

            // 3. Process Compounded Products
            $compoundedQuery = \App\Models\Control\CompoundedProduct::where('qty_hasil', '>', 0)->where('status', 'active');
            // Category might not apply directly to racikan, or we can skip category filter for racikan
            // since they don't have a direct category_id in their table. If $categoryId is provided, 
            // maybe we only include medicines and raw materials, or include racikan if we mapped them.
            // For now, if $categoryId is set, we skip racikan to avoid mapping issues unless requested.
            if (!$categoryId) {
                $compoundedBatches = $compoundedQuery->get();
                foreach ($compoundedBatches as $batch) {
                    StockOpnameItem::create([
                        'stock_opname_id' => $opname->id,
                        'compounded_product_id' => $batch->id,
                        'batch_no' => $batch->batch_no,
                        'system_stock' => $batch->qty_hasil,
                    ]);

                    // Freeze stock
                    $batch->update(['is_frozen' => true]);
                }
            }

            AuditTrail::log($userId, 'CREATE_OPNAME',
                "Membuat sesi opname baru #{$opname->id}" . ($categoryId ? " (Kategori: {$categoryId})" : "") . ($isBlindCount ? " [Blind]" : ""),
                StockOpname::class, $opname->id
            );

            return $opname;
        });
    }

    /**
     * Update physical counts and evidence for a draft opname.
     */
    public function updateCounts(StockOpname $opname, array $items, $request = null): void
    {
        foreach ($items as $index => $itemData) {
            $item = StockOpnameItem::find($itemData['id']);
            if (!$item || $item->stock_opname_id !== $opname->id) continue;

            $updateData = [
                'physical_stock' => $itemData['physical_stock'],
                'reason' => $itemData['reason'] ?? null,
            ];

            // Handle evidence photo upload
            if ($request && $request->hasFile("items.{$index}.evidence_photo")) {
                $updateData['evidence_photo'] = $request->file("items.{$index}.evidence_photo")->store('opname', 'public');
            }

            $item->update($updateData);
        }
    }

    /**
     * Finalize opname: adjust actual stock, unfreeze, log mutations.
     */
    public function finalize(StockOpname $opname, int $userId): array
    {
        $errors = [];

        // Validate: all items must have physical stock input
        $items = $opname->items()->get();
        foreach ($items as $item) {
            if ($item->physical_stock === null) {
                $errors[] = "Item batch {$item->batch_no} belum diinput stok fisik.";
                continue;
            }
            if ($item->hasDiscrepancy() && empty($item->reason)) {
                $errors[] = "Item batch {$item->batch_no} ada selisih tapi belum ada alasan.";
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return DB::transaction(function () use ($opname, $items, $userId) {
            foreach ($items as $item) {
                $difference = $item->physical_stock - $item->system_stock;
                $item->update(['difference' => $difference]);

                if ($item->warehouse_inventory_id) {
                    WarehouseInventory::where('id', $item->warehouse_inventory_id)->update(['is_frozen' => false]);
                } elseif ($item->raw_material_stock_id) {
                    RawMaterialStock::where('id', $item->raw_material_stock_id)->update(['is_frozen' => false]);
                } elseif ($item->compounded_product_id) {
                    \App\Models\Control\CompoundedProduct::where('id', $item->compounded_product_id)->update(['is_frozen' => false]);
                }

                if ($difference != 0) {
                    \App\Models\Control\InventoryLoss::create([
                        'medicine_id' => $item->medicine_id,
                        'raw_material_id' => $item->raw_material_id,
                        'compounded_product_id' => $item->compounded_product_id,
                        'warehouse_inventory_id' => $item->warehouse_inventory_id,
                        'raw_material_stock_id' => $item->raw_material_stock_id,
                        'qty_lost' => abs($difference),
                        'tipe_loss' => $difference < 0 ? 'stock_opname_shortage' : 'stock_opname_surplus',
                        'reason' => $item->reason ?: 'Selisih Stock Opname #' . $opname->id,
                        'status' => 'pending',
                        'user_id' => $userId,
                    ]);
                }

                if ($difference != 0) {
                    AuditTrail::log($userId, 'OPNAME_ADJUSTMENT',
                        "Opname #{$opname->id}: batch {$item->batch_no} sistem={$item->system_stock} fisik={$item->physical_stock} selisih={$difference}. Alasan: {$item->reason}",
                        StockOpnameItem::class, $item->id
                    );
                }
            }

            $opname->update([
                'status' => 'completed',
                'finalized_at' => now(),
                'finalized_by' => $userId,
            ]);

            AuditTrail::log($userId, 'FINALIZE_OPNAME',
                "Finalisasi opname #{$opname->id}",
                StockOpname::class, $opname->id
            );

            return ['success' => true, 'errors' => []];
        });
    }

    /**
     * Cancel opname: unfreeze all batches.
     */
    public function cancel(StockOpname $opname, int $userId): void
    {
        DB::transaction(function () use ($opname, $userId) {
            $items = StockOpnameItem::where('stock_opname_id', $opname->id)->get();
            foreach ($items as $item) {
                if ($item->warehouse_inventory_id) {
                    WarehouseInventory::where('id', $item->warehouse_inventory_id)->update(['is_frozen' => false]);
                } elseif ($item->raw_material_stock_id) {
                    RawMaterialStock::where('id', $item->raw_material_stock_id)->update(['is_frozen' => false]);
                } elseif ($item->compounded_product_id) {
                    \App\Models\Control\CompoundedProduct::where('id', $item->compounded_product_id)->update(['is_frozen' => false]);
                }
            }

            $opname->update(['status' => 'cancelled']);

            AuditTrail::log($userId, 'CANCEL_OPNAME', "Membatalkan sesi opname #{$opname->id}", StockOpname::class, $opname->id);
        });
    }
}
