<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'harga_jual_saat_ini',
        'harga_jual_akhir',
        'status_price_alert',
        'status_approval',
        'status_gate',
        'status', // in_transit, received
        'admin_note',
        'qty_fisik_verify',
        'verify_note',
        'revised_by',
        'revised_at',
        'is_retur',
        'alasan_retur',
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
            'harga_jual_saat_ini' => 'decimal:2',
            'harga_jual_akhir' => 'decimal:2',
            'is_retur' => 'boolean',
            'revised_at' => 'datetime',
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

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }

    public function returnForm(): HasOne
    {
        return $this->hasOne(ReturnForm::class);
    }

    public function warehouseInventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WarehouseInventory::class, 'incoming_stock_id');
    }

    /**
     * Get the total initial pieces received in this invoice (smallest unit).
     * This value remains CONSTANT throughout the lifespan of the batch/faktur,
     * regardless of sales or retur.
     */
    public function getInitialTotalPiecesAttribute(): float
    {
        $q1 = (float) $this->qty_masuk;
        $q2 = (float) $this->mid_to_big_ratio;
        $q3 = $this->mid_unit ? (float) $this->small_to_mid_ratio : (float) $this->unit_conversion_factor;

        $total = 1;
        $activeLevels = 0;

        if ($q1 > 0) {
            $total *= $q1;
            $activeLevels++;
        }
        if ($q2 > 0) {
            $total *= $q2;
            $activeLevels++;
        }
        if ($q3 > 0) {
            $total *= $q3;
            $activeLevels++;
        }

        return $activeLevels > 0 ? $total : (float) ($this->qty_converted ?: 1);
    }

    /**
     * Calculate HPP for the smallest unit (e.g. per tablet / per strip).
     * Formula Resmi Farmasi (Vmedis):
     * HPP per Satuan = Harga Beli Netto Satuan + Biaya Tambahan Satuan
     * 
     * di mana:
     * - Harga Beli Netto sudah memperhitungkan diskon supplier dan PPN dari PBF.
     * - Biaya Tambahan (ongkir/handling) dialokasikan per unit awal jika ada.
     * - Nilai HPP KONSTAN dan tidak berubah seiring berkurangnya stok sisa.
     */
    public function calculateHppSatuan(): float
    {
        $initialPcs = $this->initial_total_pieces ?: 1;

        // Subtotal setelah diskon supplier
        if ($this->jumlah_harga > 0) {
            $subtotalNetto = (float) $this->jumlah_harga;
        } else {
            $diskonFactor = 1 - ((float) $this->diskon_persen / 100);
            $qtyMasuk = (float) ($this->qty_masuk ?: 1);
            $subtotalNetto = (float) $this->harga_beli_satuan * $diskonFactor * $qtyMasuk;
        }

        // PPN dari PBF
        $ppnMultiplier = 1 + ((float) $this->ppn_persen / 100);
        $totalFakturWithPpn = $subtotalNetto * $ppnMultiplier;

        // Harga Beli Netto per satuan terkecil
        $hbNettoSatuan = $totalFakturWithPpn / $initialPcs;

        // Biaya Tambahan (transportasi/penyimpanan/ongkir per unit)
        $biayaTambahanSatuan = 0;
        if (!empty($this->biaya_tambahan) && $this->biaya_tambahan > 0) {
            $biayaTambahanSatuan = (float) $this->biaya_tambahan / $initialPcs;
        }

        return round($hbNettoSatuan + $biayaTambahanSatuan, 2);
    }

    /**
     * Calculate final selling price per smallest unit.
     */
    public function calculateSellingPrice(): float
    {
        $hpp = $this->calculateHppSatuan();
        $margin = $hpp * ($this->margin_persen / 100);

        return round($hpp + $margin, 2);
    }

    /**
     * Boot: auto-calculate prices and unit conversion before saving.
     */
    protected static function booted(): void
    {
        static::saving(function (IncomingStock $stock) {
            // Only recalculate converted qty if the source input fields change
            if ($stock->isDirty(['qty_masuk', 'mid_to_big_ratio', 'small_to_mid_ratio', 'unit_conversion_factor']) || is_null($stock->qty_converted)) {
                // Multi-Tier Multiplication Logic (Flexible skipping)
                $q1 = (float) $stock->qty_masuk;
                $q2 = (float) $stock->mid_to_big_ratio;
                $q3 = $stock->mid_unit ? (float) $stock->small_to_mid_ratio : (float) $stock->unit_conversion_factor;

                $total = 1;
                $activeLevels = 0;

                if ($q1 > 0) {
                    $total *= $q1;
                    $activeLevels++;
                }
                if ($q2 > 0) {
                    $total *= $q2;
                    $activeLevels++;
                }
                if ($q3 > 0) {
                    $total *= $q3;
                    $activeLevels++;
                }

                $stock->qty_converted = $activeLevels > 0 ? $total : 0;
            }

            // Recalculate price if relevant fields change
            if ($stock->isDirty(['harga_beli_satuan', 'diskon_persen', 'ppn_persen', 'margin_persen']) || is_null($stock->harga_jual_akhir)) {
                $stock->harga_jual_akhir = (string) $stock->calculateSellingPrice();
                $stock->status_price_alert = $stock->determinePriceAlert();
            }
        });
    }

    // Gate Scopes
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

    public function scopeApproved($query)
    {
        return $query->where('status_gate', 'active');
    }

    /**
     * Determine if there is a significant price increase compared to the last purchase.
     */
    public function determinePriceAlert(): string
    {
        // Get the latest approved incoming stock for this medicine
        $lastStock = static::where('medicine_id', $this->medicine_id)
            ->where('status_gate', 'active')
            ->where('id', '!=', $this->id)
            ->latest()
            ->first();

        if (!$lastStock) {
            return 'normal'; // First time purchase
        }

        $oldPrice = (float) $lastStock->harga_beli_satuan;
        $newPrice = (float) $this->harga_beli_satuan;

        if ($newPrice > $oldPrice) {
            return 'naik';
        } elseif ($newPrice < $oldPrice) {
            return 'turun';
        }

        return 'normal';
    }
}
