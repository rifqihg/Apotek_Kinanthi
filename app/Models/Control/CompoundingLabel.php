<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompoundingLabel extends Model
{
    protected $fillable = [
        'compounding_id',
        'tanggal_racik',
        'tanggal_kadaluarsa_bud',
        'bud_hari',
        'aturan_pakai',
        'keterangan',
        'nama_pasien',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_racik' => 'date',
            'tanggal_kadaluarsa_bud' => 'date',
            'bud_hari' => 'integer',
        ];
    }

    /**
     * Parent compounding submission.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompoundingSubmission::class, 'compounding_id');
    }

    /**
     * Calculate BUD (Beyond Use Date) from tanggal_racik + bud_hari.
     */
    public function calculateBUD(): ?string
    {
        if ($this->tanggal_racik) {
            return $this->tanggal_racik->addDays($this->bud_hari)->toDateString();
        }
        return null;
    }

    /**
     * Boot: auto-calculate BUD before saving.
     */
    protected static function booted(): void
    {
        static::saving(function (CompoundingLabel $label) {
            if ($label->tanggal_racik && $label->bud_hari) {
                $label->tanggal_kadaluarsa_bud = $label->tanggal_racik->copy()->addDays($label->bud_hari);
            }
        });
    }
}
