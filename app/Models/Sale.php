<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'kasir_id',
        'prescription_id',
        'sale_type',
        'total_amount',
        'discount_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',

        'paid_at' => 'datetime',
        'sale_type' => 'string',
        'payment_method' => 'string',
        'status' => 'string',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($sale) {
            if (!empty($sale->sale_number)) {
                return;
            }

            $date = now()->format('Ymd');
            $prefix = 'INV-' . $date . '-';

            // Kunci pesimistik pada record terakhir hari ini untuk mencegah tabrakan ID
            $lastSale = static::where('sale_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $nextSequence = 1;
            if ($lastSale && preg_match('/-(\d+)$/', $lastSale->sale_number, $matches)) {
                $nextSequence = (int) $matches[1] + 1;
            }

            do {
                $candidate = $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
                $exists = static::where('sale_number', $candidate)->exists();
                if ($exists) {
                    $nextSequence++;
                }
            } while ($exists);

            $sale->sale_number = $candidate;
        });
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
