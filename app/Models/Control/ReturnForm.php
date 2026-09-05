<?php

namespace App\Models\Control;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnForm extends Model
{
    protected $fillable = [
        'incoming_stock_id', 'supplier_id', 'qty_retur',
        'alasan_retur', 'status', 'printed_at',
        'acknowledged_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function incomingStock(): BelongsTo
    {
        return $this->belongsTo(IncomingStock::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
}
