<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    protected $fillable = [
        'category_id',
        'nama_obat',
        'kode_obat',
        'satuan_stok',
        'kategori',
        'satuan_besar',
        'satuan_menengah',
        'rasio_konversi',
        'base_unit',
        'large_unit',
        'conversion_factor',
        'stok_total',
        'threshold_min',
        'deskripsi',
        'foto_obat',
        'selling_price_large',
        'selling_price_mid',
        'selling_price',
    ];

    protected $appends = [
        'satuan_terkecil',
    ];

    public function getSatuanTerkecilAttribute(): string
    {
        return $this->satuan_stok ?: ($this->base_unit ?: 'Pcs');
    }

    protected function casts(): array
    {
        return [
            'stok_total' => 'decimal:2',
            'threshold_min' => 'integer',
            'rasio_konversi' => 'integer',
            'conversion_factor' => 'decimal:2',
            'selling_price_large' => 'decimal:2',
            'selling_price_mid' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Incoming stocks for this medicine.
     */
    public function incomingStocks(): HasMany
    {
        return $this->hasMany(IncomingStock::class);
    }

    /**
     * Compounding details using this medicine.
     */
    public function compoundingDetails(): HasMany
    {
        return $this->hasMany(CompoundingDetail::class);
    }

    /**
     * Warehouse inventory entries for this medicine.
     */
    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    /**
     * Sale items for this medicine.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(\App\Models\SaleItem::class);
    }

    /**
     * Check if stock is below minimum threshold.
     */
    public function isBelowThreshold(): bool
    {
        return $this->stok_total < $this->threshold_min;
    }

    /**
     * Scope: medicines below threshold.
     */
    public function scopeBelowThreshold($query)
    {
        return $query->whereColumn('stok_total', '<', 'threshold_min');
    }

    /**
     * Recalculate total stock from all locations (Karantina + Gudang Aktif + Etalase).
     * Stok Total = Stok Karantina (Pending) + Stok Aktif Gudang + Stok Etalase.
     */
    public function recalculateStock(): void
    {
        $stokAktifGudang = (float) $this->warehouseInventories()->sum('stock_on_hand');

        $transferredIds = \App\Models\Control\WarehouseInventory::whereNotNull('incoming_stock_id')
            ->pluck('incoming_stock_id')
            ->toArray();

        $stokKarantina = (float) $this->incomingStocks()
            ->whereNotIn('id', $transferredIds)
            ->get()
            ->sum(function($stock) {
                return $stock->qty_fisik_verify !== null ? $stock->qty_fisik_verify : $stock->qty_converted;
            });

        $stokEtalase = 0.00;

        $this->update([
            'stok_total' => $stokKarantina + $stokAktifGudang + $stokEtalase,
        ]);
    }
}
