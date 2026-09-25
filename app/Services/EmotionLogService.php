<?php

namespace App\Services;

use App\Models\EmotionLog;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class EmotionLogService
{
    public function create(Patient $patient, array $data): EmotionLog
    {
        return DB::transaction(function () use ($patient, $data) {
            $log = $patient->emotionLogs()->create([
                'mood_category_id' => $data['mood_category_id'],
                'occurred_at' => $data['occurred_at'],
                'intensity' => $data['intensity'] ?? null,
                'situation' => $data['situation'],
                'action' => $data['action'],
                'automatic_thought' => $data['automatic_thought'] ?? null,
            ]);

            $log->feelings()->sync($data['feeling_ids']);

            return $log;
        });
    }

    public function update(EmotionLog $log, array $data): EmotionLog
    {
        return DB::transaction(function () use ($log, $data) {
            $log->update([
                'mood_category_id' => $data['mood_category_id'],
                'occurred_at' => $data['occurred_at'],
                'intensity' => $data['intensity'] ?? null,
                'situation' => $data['situation'],
                'action' => $data['action'],
                'automatic_thought' => $data['automatic_thought'] ?? null,
            ]);

            $log->feelings()->sync($data['feeling_ids']);

            return $log;
        });
    }
}
