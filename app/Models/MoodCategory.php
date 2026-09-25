<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoodCategory extends Model
{
    /** @use HasFactory<\Database\Factories\MoodCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'color',
        'order',
    ];

    public function feelings(): HasMany
    {
        return $this->hasMany(Feeling::class);
    }

    public function emotionLogs(): HasMany
    {
        return $this->hasMany(EmotionLog::class);
    }
}
