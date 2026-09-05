<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id', 'medicine_id', 'raw_material_id',
        'warehouse_inventory_id', 'raw_material_stock_id', 'compounded_product_id',
        'batch_no', 'system_stock', 'physical_stock', 'difference', 'reason', 'evidence_photo',
    ];

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventory_id');
    }

    public function rawMaterialBatch(): BelongsTo
    {
        return $this->belongsTo(RawMaterialStock::class, 'raw_material_stock_id');
    }

    public function compoundedProduct(): BelongsTo
    {
        return $this->belongsTo(CompoundedProduct::class, 'compounded_product_id');
    }

    protected function casts(): array
    {
        return [
            'system_stock' => 'decimal:2',
            'physical_stock' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function hasDiscrepancy(): bool
    {
        return abs($this->difference) > 0.001;
    }

    /**
     * Boot: auto-calculate difference before saving.
     */
    protected static function booted(): void
    {
        static::saving(function (StockOpnameItem $item) {
            if ($item->physical_stock !== null) {
                $item->difference = round($item->physical_stock - $item->system_stock, 4);
            }
        });
    }
}
