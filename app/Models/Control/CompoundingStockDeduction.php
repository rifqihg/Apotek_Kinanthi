<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CompoundingStockDeduction extends Model
{
    protected $fillable = [
        'compounding_detail_id',
        'stock_source_id',
        'stock_source_type',
        'qty_deducted',
    ];

    protected function casts(): array
    {
        return [
            'qty_deducted' => 'decimal:2',
        ];
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(CompoundingDetail::class, 'compounding_detail_id');
    }

    /**
     * Get the source of the stock (RawMaterialStock or WarehouseInventory).
     */
    public function stockSource(): MorphTo
    {
        return $this->morphTo('stockSource', 'stock_source_type', 'stock_source_id');
    }
}
