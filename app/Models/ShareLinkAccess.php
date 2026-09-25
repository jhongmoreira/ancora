<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLinkAccess extends Model
{
    /** @use HasFactory<\Database\Factories\ShareLinkAccessFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'ip_address',
        'user_agent',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Rótulo simples de dispositivo/navegador a partir do user agent —
     * heurística leve (sem dependência externa), suficiente para o paciente
     * reconhecer "de onde" foi o acesso.
     */
    public function getDeviceLabelAttribute(): string
    {
        $ua = $this->user_agent ?? '';

        if ($ua === '') {
            return 'Dispositivo desconhecido';
        }

        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Edg/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/') => 'Safari',
            default => null,
        };

        return match (true) {
            $browser && $os => "{$browser} em {$os}",
            (bool) $browser => $browser,
            (bool) $os => $os,
            default => 'Dispositivo desconhecido',
        };
    }
}
