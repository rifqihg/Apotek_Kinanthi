<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property float|string|null $harga_jual_akhir
 */
class IncomingStock extends Model
{
    protected $fillable = [
        'faktur_no',
        'supplier_id',
        'medicine_id',
        'batch_no',
        'expired_date',
        'qty_masuk',
        'satuan_input',
        'mid_unit',
        'mid_to_big_ratio',
        'small_to_mid_ratio',
        'qty_converted',
        'unit_conversion_factor',
        'harga_beli_satuan',
        'diskon_persen',
        'jumlah_harga',
        'ppn_persen',
        'margin_persen',
        'harga_jual_akhir',
        'status_approval',
        'status_gate',
        'admin_note',
        'qty_fisik_verify',
        'verify_note',
        'tanggal_faktur',
        'foto_faktur_path',
    ];

    protected function casts(): array
    {
        return [
            'expired_date' => 'date',
            'tanggal_faktur' => 'date',
            'qty_masuk' => 'integer',
            'mid_to_big_ratio' => 'integer',
            'small_to_mid_ratio' => 'integer',
            'unit_conversion_factor' => 'integer',
            'qty_converted' => 'integer',
            'qty_fisik_verify' => 'integer',
            'harga_beli_satuan' => 'decimal:2',
            'diskon_persen' => 'decimal:2',
            'jumlah_harga' => 'decimal:2',
            'ppn_persen' => 'decimal:2',
            'margin_persen' => 'decimal:2',
            'harga_jual_akhir' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function returnForm(): HasOne
    {
        return $this->hasOne(ReturnForm::class);
    }

    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class, 'incoming_stock_id');
    }

    public function calculateConvertedQty(): int
    {
        $q1 = (float) $this->qty_masuk;
        $q2 = (float) $this->mid_to_big_ratio;
        $q3 = $this->mid_unit ? (float) $this->small_to_mid_ratio : (float) $this->unit_conversion_factor;

        $total = 1;
        $active = false;
        foreach ([$q1, $q2, $q3] as $q) {
            if ($q > 0) {
                $total *= $q;
                $active = true;
            }
        }

        return $active ? (int) $total : 0;
    }

    public function getInitialTotalPiecesAttribute(): float
    {
        $total = $this->calculateConvertedQty();
        return $total > 0 ? (float) $total : (float) ($this->qty_converted ?: 1);
    }

    public function calculateHppSatuan(): float
    {
        $initialPcs = $this->initial_total_pieces ?: 1;

        if ($this->jumlah_harga > 0) {
            $subtotalNetto = (float) $this->jumlah_harga;
        } else {
            $diskonFactor = 1 - ((float) $this->diskon_persen / 100);
            $qtyMasuk = (float) ($this->qty_masuk ?: 1);
            $subtotalNetto = (float) $this->harga_beli_satuan * $diskonFactor * $qtyMasuk;
        }

        $totalFakturWithPpn = $subtotalNetto * (1 + ((float) $this->ppn_persen / 100));
        $hbNettoSatuan = $totalFakturWithPpn / $initialPcs;

        $biayaTambahanSatuan = (!empty($this->biaya_tambahan) && $this->biaya_tambahan > 0)
            ? (float) $this->biaya_tambahan / $initialPcs
            : 0;

        return round($hbNettoSatuan + $biayaTambahanSatuan, 2);
    }

    public function calculateHargaBeliSatuan(): float
    {
        $initialPcs = $this->initial_total_pieces ?: 1;

        if ($this->jumlah_harga > 0) {
            $subtotalNetto = (float) $this->jumlah_harga;
        } else {
            $diskonFactor = 1 - ((float) $this->diskon_persen / 100);
            $qtyMasuk = (float) ($this->qty_masuk ?: 1);
            $subtotalNetto = (float) $this->harga_beli_satuan * $diskonFactor * $qtyMasuk;
        }

        return round($subtotalNetto / $initialPcs, 2);
    }

    public function calculateSellingPrice(): float
    {
        $hpp = $this->calculateHppSatuan();
        return round($hpp + ($hpp * ($this->margin_persen / 100)), 2);
    }

    protected static function booted(): void
    {
        static::saving(function (IncomingStock $stock) {
            if ($stock->isDirty(['qty_masuk', 'mid_to_big_ratio', 'small_to_mid_ratio', 'unit_conversion_factor']) || is_null($stock->qty_converted)) {
                $stock->qty_converted = $stock->calculateConvertedQty();
            }

            if ($stock->isDirty(['harga_beli_satuan', 'diskon_persen', 'ppn_persen', 'margin_persen']) || is_null($stock->harga_jual_akhir)) {
                $stock->harga_jual_akhir = (string) $stock->calculateSellingPrice();
            }
        });
    }

    public function scopePendingVerify($query)
    {
        return $query->where('status_gate', 'pending_admin_verify');
    }

    public function scopePendingAdminFinal($query)
    {
        return $query->where('status_gate', 'pending_admin_final');
    }

    public function scopeActive($query)
    {
        return $query->where('status_gate', 'active');
    }
}
