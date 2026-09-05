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
            $date = now()->format('Ymd');
            // Count prescriptions created today to
            // generate the 4-digit sequence
            $count = static::whereDate('created_at', now())
                           ->count() + 1;
            $prescription->prescription_number =
                'RX-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        });
    }

    // Accessor for Indonesian status labels
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'baru'       => 'Baru',
            'diproses'   => 'Sedang Diproses',
            'siap_ambil' => 'Siap Diambil',
            'selesai'    => 'Selesai',
            'batal'      => 'Dibatalkan',
            default      => ucfirst($this->status),
        };
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
