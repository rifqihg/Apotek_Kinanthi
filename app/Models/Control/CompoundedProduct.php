<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompoundedProduct extends Model
{
    protected $fillable = [
        'compounding_submission_id',
        'nama_produk',
        'batch_no',
        'tanggal_racik',
        'bud_date',
        'qty_hasil',
        'actual_yield',
        'satuan',
        'base_cost',
        'hpp_per_unit',
        'harga_jual',
        'status',
        'pricing_status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_racik' => 'date',
            'bud_date' => 'date',
            'qty_hasil' => 'decimal:2',
            'hpp_per_unit' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'pricing_status' => 'string',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompoundingSubmission::class, 'compounding_submission_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
