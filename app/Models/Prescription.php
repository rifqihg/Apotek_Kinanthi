<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_name',
        'clinic_name',
        'prescription_date',
        'status',
        'handled_by',
        'notes',
    ];
    // NOTE: prescription_number is NOT in fillable
    // because it is auto-generated in boot()

    protected $casts = [
        'prescription_date' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($prescription) {
            if (!empty($prescription->prescription_number)) {
                return;
            }

            $date = now()->format('Ymd');
            $prefix = 'RX-' . $date . '-';

            // Kunci pesimistik pada record terakhir hari ini untuk mencegah tabrakan nomor resep
            $lastRx = static::where('prescription_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $nextSequence = 1;
            if ($lastRx && preg_match('/-(\d+)$/', $lastRx->prescription_number, $matches)) {
                $nextSequence = (int) $matches[1] + 1;
            }

            do {
                $candidate = $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
                $exists = static::where('prescription_number', $candidate)->exists();
                if ($exists) {
                    $nextSequence++;
                }
            } while ($exists);

            $prescription->prescription_number = $candidate;
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}
