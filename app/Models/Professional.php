<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Professional extends Model
{
    /** @use HasFactory<\Database\Factories\ProfessionalFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'contact',
        'notes',
    ];

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
