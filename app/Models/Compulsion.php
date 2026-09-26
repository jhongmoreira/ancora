<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compulsão cadastrada pelo paciente (ex.: "Pornografia") — docs/14.
 * Arquivar (archived_at) esconde do registro sem apagar o histórico.
 */
class Compulsion extends Model
{
    protected $fillable = [
        'patient_id',
        'name',
        'description',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CompulsionLog::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
