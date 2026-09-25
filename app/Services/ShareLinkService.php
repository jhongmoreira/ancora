<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\ShareLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareLinkService
{
    /**
     * Gera (ou substitui) o link de compartilhamento do paciente.
     *
     * @return array{link: ShareLink, pin: string}
     */
    public function generate(Patient $patient, Carbon $expiresAt): array
    {
        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $link = ShareLink::updateOrCreate(
            ['patient_id' => $patient->id],
            [
                'token' => Str::random(48),
                'pin_hash' => Hash::make($pin),
                'expires_at' => $expiresAt,
            ]
        );

        return ['link' => $link, 'pin' => $pin];
    }

    public function revoke(Patient $patient): void
    {
        $patient->shareLink()->delete();
    }

    public function defaultExpiration(): Carbon
    {
        return now()->setTime(23, 59);
    }
}
