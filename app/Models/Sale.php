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
            $date = now()->format('Ymd');
            $count = static::whereDate('created_at', now())->count() + 1;
            $sale->sale_number = 'INV-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
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
