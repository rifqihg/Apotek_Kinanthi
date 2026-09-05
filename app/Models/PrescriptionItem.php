<?php
namespace App\Models;

use App\Models\Control\Medicine;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\CompoundingSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id',
        'item_type',
        'medicine_id',
        'compounded_product_id',
        'custom_formula_name',
        'quantity',
        'unit',
        'usage_instructions',
        'status',
        'compounding_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function compoundedProduct(): BelongsTo
    {
        return $this->belongsTo(CompoundedProduct::class);
    }

    public function compoundingSubmission(): BelongsTo
    {
        // References CompoundingSubmission model
        // which uses compounding_submissions table
        return $this->belongsTo(
            CompoundingSubmission::class,
            'compounding_id'
        );
    }
}
