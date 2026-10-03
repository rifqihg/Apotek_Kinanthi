<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompoundingFormula extends Model
{
    protected $fillable = [
        'nama_resep',
        'kategori_sediaan',
        'target_qty',
        'target_unit',
        'signa_default',
        'bud_days',
        'instruksi_racik',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'target_qty' => 'decimal:2',
            'bud_days' => 'integer',
        ];
    }

    /**
     * The ingredients of this formula.
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(FormulaIngredient::class, 'formula_id');
    }

    /**
     * Submissions using this formula.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(CompoundingSubmission::class, 'formula_id');
    }

    /**
     * Scope: only active formulas.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
