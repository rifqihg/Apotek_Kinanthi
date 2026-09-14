<?php

namespace App\Observers;

use App\Models\Control\RawMaterialStock;
use App\Models\Control\StockMutation;
use App\Models\Control\RawMaterial;

class RawMaterialStockObserver
{
    private const TYPE_MAP = [
        'in' => 'Masuk',
        'out' => 'Keluar',
        'adjustment' => 'Penyesuaian',
        'return' => 'Retur',
        'destruction' => 'Pemusnahan',
        'OPNAME_ADJUSTMENT' => 'Penyesuaian Opname',
        'opname_adjustment' => 'opname_adjustment',
    ];

    public function created(RawMaterialStock $stock): void
    {
        if ($stock->status === 'active' && $stock->stock_on_hand > 0) {
            $this->logMutation($stock, (float) $stock->stock_on_hand, 'in');
        }
    }

    public function updated(RawMaterialStock $stock): void
    {
        // 1. Handle Status Activation (The "Gate 2" moment)
        if ($stock->isDirty('status') && $stock->status === 'active') {
            if ($stock->stock_on_hand > 0) {
                $this->logMutation($stock, (float) $stock->stock_on_hand, 'Masuk');
            }
            return;
        }

        // 2. Handle normal quantity changes for already active stock
        if ($stock->status === 'active' && $stock->isDirty('stock_on_hand')) {
            $diff = (float) ($stock->stock_on_hand ?? 0) - (float) ($stock->getOriginal('stock_on_hand') ?? 0);
            if ($diff != 0) {
                $this->logMutation($stock, abs($diff), $diff > 0 ? 'Masuk' : 'Keluar');
            }
        }
    }

    protected function logMutation(RawMaterialStock $stock, ?float $qty, string $defaultType): void
    {
        if ($qty === null || $qty == 0) return;

        $context = $stock->mutationContext;
        $finalType = $context['type'] ?? $defaultType;

        StockMutation::create([
            'raw_material_stock_id' => $stock->id,
            'item_id'               => $stock->raw_material_id,
            'item_type'             => RawMaterial::class,
            'type'                  => self::TYPE_MAP[$finalType] ?? $finalType,
            'quantity'              => $qty,
            'balance_after'         => $stock->stock_on_hand,
            'reference_type'        => $context['reference_type'] ?? null,
            'reference_id'          => $context['reference_id'] ?? null,
            'note'                  => $context['note'] ?? null,
        ]);
    }
}
