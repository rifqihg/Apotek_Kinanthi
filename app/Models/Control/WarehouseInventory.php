<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function mutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'location_id');
    }

    public function incomingStock(): BelongsTo
    {
        return $this->belongsTo(IncomingStock::class);
    }

    public function scopeGudang($query)
    {
        return $query->where('location_type', 'gudang');
    }

    public function scopeKasir($query, $kasirId = null)
    {
        return $query->where('location_type', 'kasir')
            ->when($kasirId, fn($q) => $q->where('location_id', $kasirId));
    }

    public function scopeNotExpired($query)
    {
        return $query->where($this->qualifyColumn('expired_date'), '>', now());
    }

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
