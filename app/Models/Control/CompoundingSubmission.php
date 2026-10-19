<?php

namespace App\Models\Control;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
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
        'jasa_racik',
        'biaya_kemasan',
        'total_hpp_racikan',
        'harga_jual_racikan',
        'target_quantity',
        'target_unit',
        'expected_yield',
        'total_net_mass',
        'unit_weight_target',
        'filler_qty',
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
            'jasa_racik' => 'decimal:2',
            'biaya_kemasan' => 'decimal:2',
            'total_hpp_racikan' => 'decimal:2',
            'harga_jual_racikan' => 'decimal:2',
            'total_net_mass' => 'decimal:2',
            'unit_weight_target' => 'decimal:2',
            'filler_qty' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(CompoundingFormula::class, 'formula_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CompoundingDetail::class, 'compounding_id');
    }

    public function label(): HasOne
    {
        return $this->hasOne(CompoundingLabel::class, 'compounding_id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'prescription_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class, 'prescription_item_id');
    }

    public function compoundedProduct(): HasOne
    {
        return $this->hasOne(CompoundedProduct::class, 'compounding_submission_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
