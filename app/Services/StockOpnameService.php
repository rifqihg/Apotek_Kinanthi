<?php

namespace App\Services;

use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\InventoryLoss;
use App\Models\Control\RawMaterialStock;
use App\Models\Control\StockOpname;
use App\Models\Control\StockOpnameItem;
use App\Models\Control\WarehouseInventory;
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
                'created_by'     => $userId,
                'category_id'    => $categoryId,
                'opname_date'    => now()->toDateString(),
                'is_blind_count' => $isBlindCount,
                'status'         => 'draft',
            ]);

            $itemsToInsert = [];

            // 1. Process Medicines (Bulk Insert & Mass Update) - Hanya opname stok gudang agar tidak membekukan kasir
            $medicinesQuery = WarehouseInventory::where('stock_on_hand', '>', 0)
                ->where('status', 'active')
                ->where(fn($q) => $q->where('location_type', '!=', 'kasir')->orWhereNull('location_type'));
            if ($categoryId) {
                $medicinesQuery->whereHas('medicine', fn($q) => $q->where('category_id', $categoryId));
            }
            $medicineBatches = $medicinesQuery->get(['id', 'medicine_id', 'batch_no', 'stock_on_hand']);
            if ($medicineBatches->isNotEmpty()) {
                $now = now();
                foreach ($medicineBatches as $batch) {
                    $itemsToInsert[] = [
                        'stock_opname_id'        => $opname->id,
                        'medicine_id'            => $batch->medicine_id,
                        'warehouse_inventory_id' => $batch->id,
                        'raw_material_id'        => null,
                        'raw_material_stock_id'  => null,
                        'compounded_product_id'  => null,
                        'batch_no'               => $batch->batch_no,
                        'system_stock'           => $batch->stock_on_hand,
                        'physical_stock'         => null,
                        'difference'             => 0,
                        'reason'                 => null,
                        'evidence_photo'         => null,
                        'created_at'             => $now,
                        'updated_at'             => $now,
                    ];
                }
                WarehouseInventory::whereIn('id', $medicineBatches->pluck('id'))->update(['is_frozen' => true]);
            }

            // 2. Process Raw Materials (Bulk Insert & Mass Update)
            $materialsQuery = RawMaterialStock::where('stock_on_hand', '>', 0)->where('status', 'active');
            if ($categoryId) {
                $materialsQuery->whereHas('rawMaterial', fn($q) => $q->where('category_id', $categoryId));
            }
            $materialBatches = $materialsQuery->get(['id', 'raw_material_id', 'batch_no', 'stock_on_hand']);
            if ($materialBatches->isNotEmpty()) {
                $now = now();
                foreach ($materialBatches as $batch) {
                    $itemsToInsert[] = [
                        'stock_opname_id'        => $opname->id,
                        'medicine_id'            => null,
                        'warehouse_inventory_id' => null,
                        'raw_material_id'        => $batch->raw_material_id,
                        'raw_material_stock_id'  => $batch->id,
                        'compounded_product_id'  => null,
                        'batch_no'               => $batch->batch_no,
                        'system_stock'           => $batch->stock_on_hand,
                        'physical_stock'         => null,
                        'difference'             => 0,
                        'reason'                 => null,
                        'evidence_photo'         => null,
                        'created_at'             => $now,
                        'updated_at'             => $now,
                    ];
                }
                RawMaterialStock::whereIn('id', $materialBatches->pluck('id'))->update(['is_frozen' => true]);
            }

            // 3. Process Compounded Products (skip if category filter applies)
            if (!$categoryId) {
                $compoundedBatches = CompoundedProduct::where('qty_hasil', '>', 0)->where('status', 'active')->get(['id', 'batch_no', 'qty_hasil']);
                if ($compoundedBatches->isNotEmpty()) {
                    $now = now();
                    foreach ($compoundedBatches as $batch) {
                        $itemsToInsert[] = [
                            'stock_opname_id'        => $opname->id,
                            'medicine_id'            => null,
                            'warehouse_inventory_id' => null,
                            'raw_material_id'        => null,
                            'raw_material_stock_id'  => null,
                            'compounded_product_id'  => $batch->id,
                            'batch_no'               => $batch->batch_no,
                            'system_stock'           => $batch->qty_hasil,
                            'physical_stock'         => null,
                            'difference'             => 0,
                            'reason'                 => null,
                            'evidence_photo'         => null,
                            'created_at'             => $now,
                            'updated_at'             => $now,
                        ];
                    }
                    CompoundedProduct::whereIn('id', $compoundedBatches->pluck('id'))->update(['is_frozen' => true]);
                }
            }

            // Eksekusi Mass Bulk Insert dalam chunk 500 row
            if (!empty($itemsToInsert)) {
                foreach (array_chunk($itemsToInsert, 500) as $chunk) {
                    StockOpnameItem::insert($chunk);
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
                'reason'         => $itemData['reason'] ?? null,
            ];

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
        $items = $opname->items()->get();

        foreach ($items as $item) {
            if ($item->physical_stock === null) {
                $errors[] = "Item batch {$item->batch_no} belum diinput stok fisik.";
            } elseif ($item->hasDiscrepancy() && empty($item->reason)) {
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

                $this->unfreezeItem($item);

                if ($difference != 0) {
                    InventoryLoss::create([
                        'medicine_id'            => $item->medicine_id,
                        'raw_material_id'        => $item->raw_material_id,
                        'compounded_product_id'  => $item->compounded_product_id,
                        'warehouse_inventory_id' => $item->warehouse_inventory_id,
                        'raw_material_stock_id'  => $item->raw_material_stock_id,
                        'qty_lost'               => abs($difference),
                        'tipe_loss'              => $difference < 0 ? 'stock_opname_shortage' : 'stock_opname_surplus',
                        'reason'                 => $item->reason ?: 'Selisih Stock Opname #' . $opname->id,
                        'status'                 => 'pending',
                        'user_id'                => $userId,
                    ]);

                    AuditTrail::log($userId, 'OPNAME_ADJUSTMENT',
                        "Opname #{$opname->id}: batch {$item->batch_no} sistem={$item->system_stock} fisik={$item->physical_stock} selisih={$difference}. Alasan: {$item->reason}",
                        StockOpnameItem::class, $item->id
                    );
                }
            }

            $opname->update([
                'status'       => 'completed',
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
            foreach (StockOpnameItem::where('stock_opname_id', $opname->id)->get() as $item) {
                $this->unfreezeItem($item);
            }

            $opname->update(['status' => 'cancelled']);

            AuditTrail::log($userId, 'CANCEL_OPNAME', "Membatalkan sesi opname #{$opname->id}", StockOpname::class, $opname->id);
        });
    }

    private function unfreezeItem(StockOpnameItem $item): void
    {
        if ($item->warehouse_inventory_id) {
            WarehouseInventory::where('id', $item->warehouse_inventory_id)->update(['is_frozen' => false]);
        } elseif ($item->raw_material_stock_id) {
            RawMaterialStock::where('id', $item->raw_material_stock_id)->update(['is_frozen' => false]);
        } elseif ($item->compounded_product_id) {
            CompoundedProduct::where('id', $item->compounded_product_id)->update(['is_frozen' => false]);
        }
    }
}
