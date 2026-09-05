<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'nama_pbf',
        'alamat',
        'telepon',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Incoming stocks from this supplier.
     */
    public function incomingStocks(): HasMany
    {
        return $this->hasMany(IncomingStock::class);
    }

    /**
     * Scope: only active suppliers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
