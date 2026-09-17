<?php

namespace App\Observers;

use App\Models\Control\WarehouseInventory;
use App\Models\Control\StockMutation;
use App\Models\Control\Medicine;

class WarehouseInventoryObserver
{
    private const TYPE_MAP = [
        'in' => 'Masuk',
        'out' => 'Keluar',
        'adjustment' => 'Penyesuaian',
        'return' => 'Retur',
        'destruction' => 'Pemusnahan',
        'OPNAME_ADJUSTMENT' => 'Penyesuaian Opname',
        'opname_adjustment' => 'opname_adjustment',
        'sale' => 'Keluar',
        'sale_void' => 'Penyesuaian Masuk',
    ];

    public function created(WarehouseInventory $warehouseInventory): void
    {
        $this->logMutation($warehouseInventory, (float) $warehouseInventory->stock_on_hand, 'in');
    }

    public function updated(WarehouseInventory $warehouseInventory): void
    {
        if ($warehouseInventory->isDirty('stock_on_hand')) {
            $diff = (float) $warehouseInventory->stock_on_hand - (float) $warehouseInventory->getOriginal('stock_on_hand');
            if ($diff != 0) {
                $this->logMutation($warehouseInventory, abs($diff), $diff > 0 ? 'Masuk' : 'Keluar');
            }
        }
    }

    protected function logMutation(WarehouseInventory $inventory, float $qty, string $defaultType): void
    {
        $context = $inventory->mutationContext;
        $finalType = $context['type'] ?? $defaultType;

        if ($finalType === 'internal_transfer') {
            $finalType = in_array($defaultType, ['Masuk', 'in']) ? 'Masuk' : 'Keluar';
        }

        $totalBalanceAfter = (float) WarehouseInventory::where('medicine_id', $inventory->medicine_id)
            ->where('status', 'active')
            ->where('is_frozen', false)
            ->when($inventory->batch_no, fn($q, $b) => $q->where('batch_no', $b))
            ->sum('stock_on_hand');

        StockMutation::create([
            'warehouse_inventory_id' => $inventory->id,
            'item_id'                => $inventory->medicine_id,
            'item_type'              => Medicine::class,
            'type'                   => self::TYPE_MAP[$finalType] ?? $finalType,
            'quantity'               => $qty,
            'balance_after'          => $totalBalanceAfter,
            'reference_type'         => $context['reference_type'] ?? null,
            'reference_id'           => $context['reference_id'] ?? null,
            'note'                   => $context['note'] ?? null,
        ]);
    }
}
