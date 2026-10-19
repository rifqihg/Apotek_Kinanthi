<?php

namespace App\Models\Control;

use App\Models\User;
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
        'golongan_bahan',
        'suhu_penyimpanan',
        'satuan_pembelian',
        'satuan_dasar_penggunaan',
        'rasio_konversi',
        'stok_total',
        'harga_beli_satuan',
        'threshold_min',
        'deskripsi',
        'status',
        'creator_id',
        'density_factor',
    ];

    protected function casts(): array
    {
        return [
            'stok_total' => 'decimal:4',
            'harga_beli_satuan' => 'decimal:2',
            'threshold_min' => 'decimal:4',
            'rasio_konversi' => 'decimal:4',
            'density_factor' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(RawMaterialStock::class);
    }

    public function scopeBelowThreshold($query)
    {
        return $query->whereColumn('stok_total', '<', 'threshold_min');
    }

    public function recalculateStock(): void
    {
        $this->update([
            'stok_total' => $this->stocks()->sum('stock_on_hand'),
        ]);
    }

    public function getSatuanAttribute(): string
    {
        return $this->satuan_dasar_penggunaan ?? '';
    }
}
