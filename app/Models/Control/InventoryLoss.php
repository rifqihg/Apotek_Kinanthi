<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLoss extends Model
{
    protected $fillable = [
        'raw_material_id',
        'medicine_id',
        'compounded_product_id',
        'warehouse_inventory_id',
        'raw_material_stock_id',
        'qty_lost',
        'tipe_loss',
        'status',
        'reason',
        'evidence_path',
        'user_id',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'qty_lost' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function compoundedProduct(): BelongsTo
    {
        return $this->belongsTo(CompoundedProduct::class);
    }

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class);
    }

    public function rawMaterialStock(): BelongsTo
    {
        return $this->belongsTo(RawMaterialStock::class);
    }

    public function getItemNameAttribute(): string
    {
        return $this->medicine?->nama_obat ?? $this->compoundedProduct?->nama_produk ?? $this->rawMaterial?->nama_bahan ?? 'Unknown';
    }
}
