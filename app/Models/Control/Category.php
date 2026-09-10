<?php

namespace App\Models\Control;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
    ];

    public function medicines()
    {
        return $this->hasMany(Medicine::class);
    }

    protected $appends = ['nama_kategori'];

    public function getNamaKategoriAttribute(): ?string
    {
        return $this->name;
    }

    public function rawMaterials()
    {
        return $this->hasMany(RawMaterial::class);
    }
}
