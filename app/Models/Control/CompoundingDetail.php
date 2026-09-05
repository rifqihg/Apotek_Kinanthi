<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function deductions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompoundingStockDeduction::class, 'compounding_detail_id');
    }

    /**
     * Get the display name of the ingredient (medicine or raw material).
     */
    public function getIngredientNameAttribute(): string
    {
        if ($this->source_type === 'raw_material' && $this->rawMaterial) {
            return $this->rawMaterial->nama_bahan;
        }
        return $this->medicine?->nama_obat ?? 'Unknown';
    }

    /**
     * Calculate qty_total_deducted with wastage subtraction.
     * Formula: berat_awal - (berat_awal * wastage_percentage / 100)
     * This represents the USABLE amount after subtracting residue/penyusutan.
     * Example: 100g - (100g * 5%) = 95g usable
     */
    public function calculateTotalDeducted(): float
    {
        return round($this->qty_needed - ($this->qty_needed * $this->wastage_percentage / 100), 2);
    }

    /**
     * Boot: auto-calculate total deducted and HPP subtotal before saving.
     */
    protected static function booted(): void
    {
        static::saving(function (CompoundingDetail $detail) {
            $detail->qty_total_deducted = $detail->calculateTotalDeducted();
            $detail->subtotal_hpp = round($detail->qty_needed * $detail->harga_satuan_saat_pakai, 2);
        });
    }

    /**
     * FEFO suggestion: get batch with nearest expiry date that has stock.
     */
    public static function suggestFEFO(int $itemId, string $type = 'medicine'): Model|null
    {
        if ($type === 'raw_material') {
            return RawMaterialStock::where('raw_material_id', $itemId)
                ->where('stock_on_hand', '>', 0)
                ->where('expired_date', '>', now())
                ->orderBy('expired_date', 'asc')
                ->first();
        }

        return WarehouseInventory::where('medicine_id', $itemId)
            ->where('stock_on_hand', '>', 0)
            ->where('expired_date', '>', now())
            ->orderBy('expired_date', 'asc')
            ->first();
    }
}
