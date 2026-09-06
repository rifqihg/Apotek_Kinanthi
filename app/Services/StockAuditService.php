<?php

namespace App\Services;

use App\Models\Control\RawMaterial;
use App\Models\Control\StockLog;
use Illuminate\Support\Facades\Auth;

class StockAuditService
{
    public static function log($item, string $action, float $qtyBefore, float $qtyAfter, string $reason): StockLog
    {
        return StockLog::create([
            'action'        => $action,
            'qty_before'    => $qtyBefore,
            'qty_after'     => $qtyAfter,
            'change_amount' => $qtyAfter - $qtyBefore,
            'reason'        => $reason,
            'user_id'       => Auth::id() ?? 1,
            ($item instanceof RawMaterial ? 'raw_material_id' : 'medicine_id') => $item->id,
        ]);
    }
}
