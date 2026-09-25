<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmotionLog extends Model
{
    /** @use HasFactory<\Database\Factories\EmotionLogFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'mood_category_id',
        'occurred_at',
        'intensity',
        'situation',
        'action',
        'automatic_thought',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'intensity' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function moodCategory(): BelongsTo
    {
        return $this->belongsTo(MoodCategory::class);
    }

    public function feelings(): BelongsToMany
    {
        return $this->belongsToMany(Feeling::class, 'emotion_log_feeling');
    }
}
