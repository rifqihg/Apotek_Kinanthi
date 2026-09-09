<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'default_ppn',
        'default_margin',
    ];

    protected function casts(): array
    {
        return [
            'default_ppn' => 'decimal:2',
            'default_margin' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => cache()->forget('app_settings'));
        static::deleted(fn () => cache()->forget('app_settings'));
    }

    /**
     * Get the current settings (singleton pattern with in-memory caching).
     */
    public static function current(): self
    {
        return cache()->rememberForever('app_settings', function () {
            return self::firstOrCreate([], [
                'default_ppn' => 11.00,
                'default_margin' => 25.00,
            ]);
        });
    }
}
