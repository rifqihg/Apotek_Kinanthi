<?php

namespace App\Services;

use App\Models\Control\StockLog;
use Illuminate\Support\Facades\Auth;

class StockAuditService
{
    /**
     * Log a stock change event.
     */
    public static function log($item, string $action, float $qtyBefore, float $qtyAfter, string $reason)
    {
        $data = [
            'action' => $action,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'change_amount' => $qtyAfter - $qtyBefore,
            'reason' => $reason,
            'user_id' => Auth::id() ?? 1, // Default to admin/system if no auth
        ];

        if (get_class($item) === 'App\Models\Control\RawMaterial') {
            $data['raw_material_id'] = $item->id;
        } else {
            $data['medicine_id'] = $item->id;
        }

        return StockLog::create($data);
    }
}
