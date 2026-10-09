<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property float|string|null $qty_total_deducted
 * @property float|string|null $subtotal_hpp
 */
class CompoundingDetail extends Model
{
    protected $fillable = [
        'compounding_id', 'medicine_id', 'raw_material_id', 'source_type',
        'qty_needed', 'wastage_percentage', 'net_quantity', 'qty_total_deducted',
        'stock_batch_id', 'volume_to_mass_factor',
        'suggested_batch_no', 'suggested_expired_date',
        'harga_satuan_saat_pakai', 'subtotal_hpp',
    ];

    protected function casts(): array
    {
        return [
            'qty_needed' => 'decimal:2',
            'wastage_percentage' => 'decimal:2',
            'net_quantity' => 'decimal:2',
            'qty_total_deducted' => 'decimal:2',
            'volume_to_mass_factor' => 'decimal:2',
            'suggested_expired_date' => 'date',
            'harga_satuan_saat_pakai' => 'decimal:2',
            'subtotal_hpp' => 'decimal:2',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompoundingSubmission::class, 'compounding_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(CompoundingStockDeduction::class, 'compounding_detail_id');
    }

    public function getIngredientNameAttribute(): string
    {
        return ($this->source_type === 'raw_material' ? $this->rawMaterial?->nama_bahan : $this->medicine?->nama_obat) ?? 'Unknown';
    }

    public function calculateTotalDeducted(): float
    {
        return (float) $this->qty_needed;
    }

    protected static function booted(): void
    {
        static::saving(function (CompoundingDetail $detail) {
            $detail->qty_total_deducted = (string) $detail->calculateTotalDeducted();
            $detail->subtotal_hpp = (string) round((float) $detail->qty_needed * (float) $detail->harga_satuan_saat_pakai, 2);
        });
    }

    public static function suggestFEFO(int $itemId, string $type = 'medicine'): Model|null
    {
        $query = $type === 'raw_material'
            ? RawMaterialStock::where('raw_material_id', $itemId)
            : WarehouseInventory::where('medicine_id', $itemId)->where('location_type', 'gudang');

        return $query->where('stock_on_hand', '>', 0)
            ->where('expired_date', '>', now())
            ->orderBy('expired_date', 'asc')
            ->first();
    }
}
