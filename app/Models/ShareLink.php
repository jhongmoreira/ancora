<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class ShareLink extends Model
{
    /** @use HasFactory<\Database\Factories\ShareLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'token',
        'pin_hash',
        'expires_at',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function pinMatches(string $pin): bool
    {
        return Hash::check($pin, $this->pin_hash);
    }
}
