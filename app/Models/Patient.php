<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends Model
{
    /** @use HasFactory<\Database\Factories\PatientFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'professional_id',
        'full_name',
        'birth_date',
        'gender',
        'contact',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function emotionLogs(): HasMany
    {
        return $this->hasMany(EmotionLog::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function shareLink(): HasOne
    {
        return $this->hasOne(ShareLink::class);
    }

    public function shareLinkAccesses(): HasMany
    {
        return $this->hasMany(ShareLinkAccess::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    public function isProfileComplete(): bool
    {
        return filled($this->full_name) && filled($this->birth_date);
    }
}
