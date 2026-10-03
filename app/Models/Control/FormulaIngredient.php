<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormulaIngredient extends Model
{
    protected $fillable = [
        'formula_id',
        'source_type',
        'source_id',
        'qty_required',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'qty_required' => 'decimal:2',
        ];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(CompoundingFormula::class, 'formula_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class, 'source_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'source_id');
    }
}
