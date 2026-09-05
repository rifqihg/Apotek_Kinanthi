<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundingSubmission;
use App\Models\Control\StockConfirmation;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is Admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is Apoteker.
     */
    public function isApoteker(): bool
    {
        return $this->role === 'apoteker';
    }

    /**
     * Check if user is Kasir.
     */
    public function isKasir(): bool
    {
        return $this->role === 'kasir';
    }

    /**
     * Audit trails by this user.
     */
    public function auditTrails(): HasMany
    {
        return $this->hasMany(AuditTrail::class);
    }

    /**
     * Compounding submissions by this user (apoteker).
     */
    public function compoundingSubmissions(): HasMany
    {
        return $this->hasMany(CompoundingSubmission::class);
    }

    /**
     * Stock confirmations by this user (admin).
     */
    public function stockConfirmations(): HasMany
    {
        return $this->hasMany(StockConfirmation::class);
    }
}
