<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockConfirmation extends Model
{
    protected $fillable = [
        'reference_id',
        'reference_type',
        'user_id',
        'item_type',
        'unit_type',
        'conversion_rate',
        'qty_expected',
        'qty_received',
        'condition_note',
        'evidence_image',
        'is_confirmed',
        'confirmed_at',
    ];

    /**
     * Get the evidence image URL.
     */
    public function getEvidenceUrlAttribute(): ?string
    {
        return $this->evidence_image ? asset('storage/' . $this->evidence_image) : null;
    }

    protected function casts(): array
    {
        return [
            'qty_expected' => 'decimal:2',
            'qty_received' => 'decimal:2',
            'is_confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasDiscrepancy(): bool
    {
        return $this->qty_received !== null && abs($this->qty_expected - $this->qty_received) > 0.01;
    }

    public function getDiscrepancy(): float
    {
        return $this->qty_received !== null ? round($this->qty_received - $this->qty_expected, 2) : 0.0;
    }

    public function scopeUnconfirmed($query)
    {
        return $query->where('is_confirmed', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('reference_type', $type);
    }
}
