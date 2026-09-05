<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompoundingSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'formula_id',
        'nama_racikan',
        'status',
        'apoteker_note',
        'admin_note',
        'total_hpp_bahan',
        'biaya_tuslah',
        'total_hpp_racikan',
        'harga_jual_racikan',
        'target_quantity',
        'target_unit',
        'expected_yield',
        'total_net_mass',
        'unit_weight_target',
        'filler_qty',
        'pharmacist_id',
        'approved_at',
        'approved_by',
        'prescription_id',
        'prescription_item_id',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'total_hpp_bahan' => 'decimal:2',
            'biaya_tuslah' => 'decimal:2',
            'total_hpp_racikan' => 'decimal:2',
            'harga_jual_racikan' => 'decimal:2',
            'total_net_mass' => 'decimal:2',
            'unit_weight_target' => 'decimal:2',
            'filler_qty' => 'decimal:2',
        ];
    }

    /**
     * The apoteker who submitted this.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The formula used (optional).
     */
    public function formula(): BelongsTo
    {
        return $this->belongsTo(CompoundingFormula::class, 'formula_id');
    }

    /**
     * The admin who approved/rejected.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Detail items for this compounding.
     */
    public function details(): HasMany
    {
        return $this->hasMany(CompoundingDetail::class, 'compounding_id');
    }

    /**
     * Label/etiket for this compounding.
     */
    public function label(): HasOne
    {
        return $this->hasOne(CompoundingLabel::class, 'compounding_id');
    }

    /**
     * Scope: pending approval.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: approved.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Recalculate HPP from all detail items + tuslah.
     */
    public function recalculateHPP(): void
    {
        $totalBahan = $this->details()->sum('subtotal_hpp');
        $this->update([
            'total_hpp_bahan' => $totalBahan,
            'total_hpp_racikan' => $totalBahan + $this->biaya_tuslah,
        ]);
    }

    /**
     * The associated prescription.
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Prescription::class, 'prescription_id');
    }

    /**
     * The associated prescription item.
     */
    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PrescriptionItem::class, 'prescription_item_id');
    }
}
