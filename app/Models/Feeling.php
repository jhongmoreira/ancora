<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feeling extends Model
{
    /** @use HasFactory<\Database\Factories\FeelingFactory> */
    use HasFactory;

    protected $fillable = [
        'mood_category_id',
        'name',
        'order',
    ];

    public function moodCategory(): BelongsTo
    {
        return $this->belongsTo(MoodCategory::class);
    }

    public function emotionLogs(): BelongsToMany
    {
        return $this->belongsToMany(EmotionLog::class, 'emotion_log_feeling');
    }
}
