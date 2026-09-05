<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseInventory extends Model
{
    protected $fillable = [
        'incoming_stock_id',
        'medicine_id',
        'batch_no',
        'expired_date',
        'stock_on_hand',
        'status',
        'is_frozen',
        'shelf_location',
        'rack_location',
        'transferred_at',
        'notes',
        'location_type',
        'location_id',
    ];

    protected function casts(): array
    {
        return [
            'expired_date' => 'date',
            'transferred_at' => 'date',
            'stock_on_hand' => 'decimal:2',
            'status' => 'string',
            'is_frozen' => 'boolean',
        ];
    }

    /**
     * Mutation logs for this inventory entry.
     */
    public function mutations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    /**
     * Medicine for this inventory entry.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Kasir for this inventory entry (if location_type is 'kasir').
     */
    public function kasir(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'location_id');
    }

    /**
     * Source incoming stock (faktur) for this inventory entry.
     */
    public function incomingStock(): BelongsTo
    {
        return $this->belongsTo(IncomingStock::class);
    }

    /**
     * Scope for Gudang stock.
     */
    public function scopeGudang($query)
    {
        return $query->where('location_type', 'gudang');
    }

    /**
     * Scope for Kasir stock.
     */
    public function scopeKasir($query, $kasirId = null)
    {
        $query->where('location_type', 'kasir');
        if ($kasirId) {
            $query->where('location_id', $kasirId);
        }
        return $query;
    }

    /**
     * Check if this batch is expired.
     */
    public function isExpired(): bool
    {
        return $this->expired_date->isPast();
    }

    /**
     * Check if this batch is expiring soon (within 90 days).
     */
    public function isExpiringSoon(int $days = 90): bool
    {
        return $this->expired_date->between(now(), now()->addDays($days));
    }

    /**
     * Scope: only non-expired stock.
     */
    public function scopeNotExpired($query)
    {
        return $query->where($this->qualifyColumn('expired_date'), '>', now());
    }

    /**
     * Scope: FEFO ordering (First Expired First Out).
     */
    public function scopeFefo($query)
    {
        return $query->orderBy($this->qualifyColumn('expired_date'), 'asc');
    }

    /**
     * Temporary context for mutations.
     */
    public ?array $mutationContext = null;

    public function setMutationContext(string $type, $reference = null, ?string $note = null): self
    {
        $this->mutationContext = [
            'type' => $type,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference ? $reference->id : null,
            'note' => $note,
        ];
        return $this;
    }

    /**
     * Scope: active stock only.
     */
    public function scopeActive($query)
    {
        return $query->where($this->qualifyColumn('status'), 'active')->where($this->qualifyColumn('is_frozen'), false);
    }

    /**
     * Scope: by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where($this->qualifyColumn('status'), $status);
    }

    /**
     * Scope: Expiring soon (within X days).
     */
    public function scopeExpiringSoon($query, int $days = 90)
    {
        return $query->whereBetween($this->qualifyColumn('expired_date'), [now(), now()->addDays($days)]);
    }
}
