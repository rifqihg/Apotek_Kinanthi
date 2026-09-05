<?php

namespace App\Observers;

use App\Models\Control\RawMaterialStock;
use App\Models\Control\StockMutation;
use App\Models\Control\RawMaterial;

class RawMaterialStockObserver
{
    public function created(RawMaterialStock $stock): void
    {
        // Only log mutation if there is actual stock being initialized (e.g. from a process that sets stock_on_hand immediately)
        // In the Dual-Gate flow, initial registration has stock_on_hand = null or 0 until Gate 2.
        if ($stock->stock_on_hand !== null && $stock->stock_on_hand > 0 && $stock->status === 'active') {
            $this->logMutation($stock, (float) $stock->stock_on_hand, 'in');
        }
    }

    public function updated(RawMaterialStock $stock): void
    {
        // 1. Handle Status Activation (The "Gate 2" moment)
        if ($stock->isDirty('status') && $stock->status === 'active') {
            // This is the official entry into the system. Log the full current stock.
            if ($stock->stock_on_hand > 0) {
                $this->logMutation($stock, (float) $stock->stock_on_hand, 'Masuk');
            }
            return; // Skip normal diff check to avoid double logging
        }

        // 2. Handle normal quantity changes for already active stock
        if ($stock->status === 'active' && $stock->isDirty('stock_on_hand')) {
            $old = (float) ($stock->getOriginal('stock_on_hand') ?? 0);
            $new = (float) ($stock->stock_on_hand ?? 0);
            
            $diff = $new - $old;

            if ($diff != 0) {
                $defaultType = $diff > 0 ? 'Masuk' : 'Keluar';
                $this->logMutation($stock, abs($diff), $defaultType);
            }
        }
    }

    protected function logMutation(RawMaterialStock $stock, ?float $qty, string $defaultType): void
    {
        if ($qty === null || $qty == 0) return;
        
        $context = $stock->mutationContext;
        
        $typeMap = [
            'in' => 'Masuk',
            'out' => 'Keluar',
            'adjustment' => 'Penyesuaian',
            'return' => 'Retur',
            'destruction' => 'Pemusnahan',
            'OPNAME_ADJUSTMENT' => 'Penyesuaian Opname'
        ];

        $finalType = $context['type'] ?? $defaultType;
        if (isset($typeMap[$finalType])) {
            $finalType = $typeMap[$finalType];
        }

        StockMutation::create([
            'raw_material_stock_id' => $stock->id,
            'item_id' => $stock->raw_material_id,
            'item_type' => RawMaterial::class,
            'type' => $finalType,
            'quantity' => $qty,
            'balance_after' => $stock->stock_on_hand,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'note' => $context['note'] ?? null,
        ]);
    }
}
