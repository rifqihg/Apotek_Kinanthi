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

    /**
     * User (gudang) who confirmed.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if there's a discrepancy between expected and received qty.
     */
    public function hasDiscrepancy(): bool
    {
        if ($this->qty_received === null) {
            return false;
        }
        return abs($this->qty_expected - $this->qty_received) > 0.01;
    }

    /**
     * Get the discrepancy amount.
     */
    public function getDiscrepancy(): float
    {
        if ($this->qty_received === null) {
            return 0;
        }
        return round($this->qty_received - $this->qty_expected, 2);
    }

    /**
     * Scope: unconfirmed.
     */
    public function scopeUnconfirmed($query)
    {
        return $query->where('is_confirmed', false);
    }

    /**
     * Scope: by reference type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('reference_type', $type);
    }
}
