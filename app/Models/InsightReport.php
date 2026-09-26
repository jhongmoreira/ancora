<?php

namespace App\Models;

use App\Services\Insights\InsightDataBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relatório de insights da quinzena gerado com IA (docs/15).
 */
class InsightReport extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'patient_id',
        'period_preset',
        'period_start',
        'period_end',
        'status',
        'model',
        'prompt_version',
        'stats',
        'payload',
        'content',
        'risk_flag',
        'risk_record_ids',
        'usage',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'stats' => 'array',
            'payload' => 'array',
            'content' => 'array',
            'risk_flag' => 'boolean',
            'risk_record_ids' => 'array',
            'usage' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** Nome da opção de período usada ("Última quinzena", "Personalizado"...). */
    public function periodLabel(): string
    {
        return InsightDataBuilder::PERIODS[$this->period_preset] ?? 'Personalizado';
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
