<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawMaterial extends Model
{
    protected $fillable = [
        'category_id',
        'nama_bahan',
        'bentuk_sediaan',
        'kode_bahan',
        'satuan_ukur',
        'base_unit',
        'large_unit',
        'conversion_factor',
        'stok_total',
        'harga_beli_satuan',
        'threshold_min',
        'deskripsi',
        'status',
        'creator_id',
        'verified_by',
        'verified_at',
        'admin_gate_1_at',
        'admin_gate_2_at',
        'admin_note',
        'density_factor',
    ];

    protected function casts(): array
    {
        return [
            'stok_total' => 'decimal:2',
            'harga_beli_satuan' => 'decimal:2',
            'threshold_min' => 'decimal:2',
            'conversion_factor' => 'decimal:2',
            'density_factor' => 'decimal:2',
            'verified_at' => 'datetime',
            'admin_gate_1_at' => 'datetime',
            'admin_gate_2_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'creator_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }



    public function stocks(): HasMany
    {
        return $this->hasMany(RawMaterialStock::class);
    }

    public function isBelowThreshold(): bool
    {
        return $this->stok_total < $this->threshold_min;
    }

    public function scopeBelowThreshold($query)
    {
        return $query->whereColumn('stok_total', '<', 'threshold_min');
    }

    /**
     * Recalculate total stock from batch stocks.
     */
    public function recalculateStock(): void
    {
        $this->update([
            'stok_total' => $this->stocks()->sum('stock_on_hand'),
        ]);
    }
}
