<?php
 
namespace App\Models\Control;
 
use Illuminate\Database\Eloquent\Model;
 
class Unit extends Model
{
    protected $fillable = [
        'name',
        'conversion_factor',
        'base_unit',
    ];
 
    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:2',
        ];
    }
}
