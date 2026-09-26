<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Registro de um impulso de compulsão, com desfecho "cedeu" ou "resistiu" e
 * sentimentos antes (antecedente) e depois (consequência) — docs/14.
 */
class CompulsionLog extends Model
{
    public const OUTCOME_GAVE_IN = 'gave_in';

    public const OUTCOME_RESISTED = 'resisted';

    public const MOMENT_BEFORE = 'before';

    public const MOMENT_AFTER = 'after';

    protected $fillable = [
        'patient_id',
        'compulsion_id',
        'occurred_at',
        'outcome',
        'urge_intensity',
        'trigger',
        'automatic_thought',
        'duration_minutes',
        'coping_strategy',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'urge_intensity' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function compulsion(): BelongsTo
    {
        return $this->belongsTo(Compulsion::class);
    }

    public function feelings(): BelongsToMany
    {
        return $this->belongsToMany(Feeling::class, 'compulsion_log_feeling')->withPivot('moment');
    }

    public function feelingsBefore(): BelongsToMany
    {
        return $this->feelings()->wherePivot('moment', self::MOMENT_BEFORE);
    }

    public function feelingsAfter(): BelongsToMany
    {
        return $this->feelings()->wherePivot('moment', self::MOMENT_AFTER);
    }

    public function gaveIn(): bool
    {
        return $this->outcome === self::OUTCOME_GAVE_IN;
    }
}
