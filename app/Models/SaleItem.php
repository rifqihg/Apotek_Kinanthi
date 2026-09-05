<?php

namespace App\Models;

use App\Models\Control\CompoundedProduct;
use App\Models\Control\Medicine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'prescription_item_id',
        'item_type',
        'medicine_id',
        'compounded_product_id',
        'item_name',
        'quantity',
        'unit',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function compoundedProduct(): BelongsTo
    {
        return $this->belongsTo(CompoundedProduct::class, 'compounded_product_id');
    }
}
