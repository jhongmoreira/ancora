<?php

namespace App\Services;

use App\Models\CompulsionLog;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class CompulsionLogService
{
    public function create(Patient $patient, array $data): CompulsionLog
    {
        return DB::transaction(function () use ($patient, $data) {
            $log = $patient->compulsionLogs()->create($this->attributes($data));

            $this->syncFeelings($log, $data);

            return $log;
        });
    }

    public function update(CompulsionLog $log, array $data): CompulsionLog
    {
        return DB::transaction(function () use ($log, $data) {
            $log->update($this->attributes($data));

            $this->syncFeelings($log, $data);

            return $log;
        });
    }

    protected function attributes(array $data): array
    {
        $gaveIn = $data['outcome'] === CompulsionLog::OUTCOME_GAVE_IN;

        return [
            'compulsion_id' => $data['compulsion_id'],
            'occurred_at' => $data['occurred_at'],
            'outcome' => $data['outcome'],
            'urge_intensity' => $data['urge_intensity'],
            'trigger' => $data['trigger'],
            'automatic_thought' => $data['automatic_thought'] ?? null,
            // Duração só faz sentido quando o paciente cedeu.
            'duration_minutes' => $gaveIn ? ($data['duration_minutes'] ?? null) : null,
            'coping_strategy' => $data['coping_strategy'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * O mesmo sentimento pode aparecer antes e depois (ex.: "Ansioso"), por
     * isso o pivot é sincronizado por momento em vez de via sync().
     */
    protected function syncFeelings(CompulsionLog $log, array $data): void
    {
        $log->feelings()->detach();

        foreach ([
            CompulsionLog::MOMENT_BEFORE => $data['feelings_before'] ?? [],
            CompulsionLog::MOMENT_AFTER => $data['feelings_after'] ?? [],
        ] as $moment => $feelingIds) {
            foreach (array_unique($feelingIds) as $feelingId) {
                $log->feelings()->attach($feelingId, ['moment' => $moment]);
            }
        }
    }
}
