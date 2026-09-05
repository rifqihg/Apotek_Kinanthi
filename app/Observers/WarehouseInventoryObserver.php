<?php

namespace App\Observers;

use App\Models\Control\WarehouseInventory;
use App\Models\Control\StockMutation;
use App\Models\Control\Medicine;

class WarehouseInventoryObserver
{
    /**
     * Handle the WarehouseInventory "created" event.
     */
    public function created(WarehouseInventory $warehouseInventory): void
    {
        $this->logMutation($warehouseInventory, $warehouseInventory->stock_on_hand, 'in');
    }

    public function updated(WarehouseInventory $warehouseInventory): void
    {
        if ($warehouseInventory->isDirty('stock_on_hand')) {
            $oldStock = (float) $warehouseInventory->getOriginal('stock_on_hand');
            $newStock = (float) $warehouseInventory->stock_on_hand;
            $diff = $newStock - $oldStock;

            if ($diff != 0) {
                // Determine type based on direction
                $defaultType = $diff > 0 ? 'Masuk' : 'Keluar';
                $this->logMutation($warehouseInventory, abs($diff), $defaultType);
            }
        }
    }

    protected function logMutation(WarehouseInventory $inventory, float $qty, string $defaultType): void
    {
        $context = $inventory->mutationContext;
        
        // Map internal types to Indonesian labels for audit trail
        $typeMap = [
            'in' => 'Masuk',
            'out' => 'Keluar',
            'adjustment' => 'Penyesuaian',
            'return' => 'Retur',
            'destruction' => 'Pemusnahan',
            'OPNAME_ADJUSTMENT' => 'Penyesuaian Opname'
        ];

        $finalType = $context['type'] ?? $defaultType;
        
        // Skip logging for internal distributions (Gudang <-> Kasir)
        if ($finalType === 'internal_transfer') {
            return;
        }

        if (isset($typeMap[$finalType])) {
            $finalType = $typeMap[$finalType];
        }

        // Calculate total remaining balance across all locations (Gudang + Kasir) for this medicine & batch
        $balanceQuery = WarehouseInventory::where('medicine_id', $inventory->medicine_id)
            ->where('status', 'active')
            ->where('is_frozen', false);

        if ($inventory->batch_no) {
            $balanceQuery->where('batch_no', $inventory->batch_no);
        }

        $totalBalanceAfter = (float) $balanceQuery->sum('stock_on_hand');

        StockMutation::create([
            'warehouse_inventory_id' => $inventory->id,
            'item_id' => $inventory->medicine_id,
            'item_type' => Medicine::class,
            'type' => $finalType,
            'quantity' => $qty,
            'balance_after' => $totalBalanceAfter,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'note' => $context['note'] ?? null,
        ]);
    }

    /**
     * Handle the WarehouseInventory "deleted" event.
     */
    public function deleted(WarehouseInventory $warehouseInventory): void
    {
        //
    }

    /**
     * Handle the WarehouseInventory "restored" event.
     */
    public function restored(WarehouseInventory $warehouseInventory): void
    {
        //
    }

    /**
     * Handle the WarehouseInventory "force deleted" event.
     */
    public function forceDeleted(WarehouseInventory $warehouseInventory): void
    {
        //
    }
}
