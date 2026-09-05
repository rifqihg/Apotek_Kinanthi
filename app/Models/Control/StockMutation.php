<?php
 
namespace App\Models\Control;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class StockMutation extends Model
{
    protected $fillable = [
        'warehouse_inventory_id',
        'raw_material_stock_id',
        'item_id',
        'item_type',
        'type',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'note',
    ];
 
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }
 
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventory_id');
    }

    public function rawMaterialStock(): BelongsTo
    {
        return $this->belongsTo(RawMaterialStock::class, 'raw_material_stock_id');
    }
 
    public function item()
    {
        return $this->morphTo();
    }
}
