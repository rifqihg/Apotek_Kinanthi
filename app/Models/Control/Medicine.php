<?php

namespace App\Models\Control;

use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Medicine extends Model
{
    protected $fillable = [
        'category_id',
        'nama_obat',
        'kode_obat',
        'barcode',
        'kfa_code',
        'nie',
        'satuan_stok',
        'kategori',
        'classification',
        'satuan_besar',
        'satuan_menengah',
        'rasio_konversi',
        'base_unit',
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
            'selling_price_large' => 'decimal:2',
            'selling_price_mid' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function incomingStocks(): HasMany
    {
        return $this->hasMany(IncomingStock::class);
    }

    public function latestIncomingStock(): HasOne
    {
        return $this->hasOne(IncomingStock::class)->latestOfMany();
    }

    public function latestActiveIncomingStock(): HasOne
    {
        return $this->hasOne(IncomingStock::class)->ofMany(
            ['id' => 'max'],
            fn ($q) => $q->where('status_gate', 'active')
        );
    }

    public function earliestExpiringStock(): HasOne
    {
        return $this->hasOne(IncomingStock::class)->ofMany(
            ['expired_date' => 'min', 'id' => 'min'],
            fn ($q) => $q->whereNotNull('expired_date')
        );
    }

    public function compoundingDetails(): HasMany
    {
        return $this->hasMany(CompoundingDetail::class);
    }

    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeBelowThreshold($query)
    {
        return $query->whereColumn('stok_total', '<', 'threshold_min');
    }

    public function scopeSipnap($query)
    {
        return $query->whereIn('classification', ['narkotika', 'psikotropika']);
    }

    public function recalculateStock(): void
    {
        $stokAktifGudang = (float) $this->warehouseInventories()->sum('stock_on_hand');

        $transferredIds = $this->warehouseInventories()
            ->whereNotNull('incoming_stock_id')
            ->pluck('incoming_stock_id')
            ->toArray();

        $stokKarantina = (float) $this->incomingStocks()
            ->when(count($transferredIds) > 0, function ($q) use ($transferredIds) {
                $q->whereNotIn('id', $transferredIds);
            })
            ->selectRaw('SUM(COALESCE(qty_fisik_verify, qty_converted)) as total_karantina')
            ->value('total_karantina');

        $this->update([
            'stok_total' => $stokKarantina + $stokAktifGudang,
        ]);
    }
}
