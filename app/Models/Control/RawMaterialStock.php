<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawMaterialStock extends Model
{
    protected $fillable = [
        'raw_material_id',
        'batch_no',
        'expired_date',
        'expected_quantity',
        'actual_quantity',
        'stock_on_hand',
        'status',
        'is_frozen',
        'shelf_location',
        'rack_location',
        'notes',
    ];

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

    protected function casts(): array
    {
        return [
            'expired_date' => 'date',
            'expected_quantity' => 'decimal:2',
            'actual_quantity' => 'decimal:2',
            'stock_on_hand' => 'decimal:2',
            'status' => 'string',
            'is_frozen' => 'boolean',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_frozen', false);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expired_date', '>', now());
    }

    public function scopeFefo($query)
    {
        return $query->orderBy('expired_date', 'asc');
    }

    public function scopeExpiringSoon($query, int $days = 90)
    {
        return $query->whereBetween('expired_date', [now(), now()->addDays($days)]);
    }
}
