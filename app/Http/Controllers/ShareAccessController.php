<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ShareAccessController extends Controller
{
    protected const MAX_ATTEMPTS = 3;

    protected const DECAY_MINUTES = 30;

    public function showPin(string $token)
    {
        $link = ShareLink::where('token', $token)->first();

        if (! $link || $link->isExpired()) {
            return view('share.invalid');
        }

        if (session()->get("share_access.{$token}")) {
            return redirect()->route('share.dashboard', $token);
        }

        return view('share.pin', ['token' => $token]);
    }

    public function verifyPin(Request $request, string $token)
    {
        $link = ShareLink::where('token', $token)->first();

        if (! $link || $link->isExpired()) {
            return view('share.invalid');
        }

        $throttleKey = "share-pin:{$token}:{$request->ip()}";

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'pin' => "Muitas tentativas. Tente novamente em ".ceil($seconds / 60)." minuto(s).",
            ]);
        }

        $validated = $request->validate([
            'pin' => ['required', 'digits:6'],
        ]);

        if (! $link->pinMatches($validated['pin'])) {
            RateLimiter::hit($throttleKey, self::DECAY_MINUTES * 60);

            throw ValidationException::withMessages([
                'pin' => 'PIN incorreto.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->put("share_access.{$token}", true);

        $link->patient->shareLinkAccesses()->create([
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'accessed_at' => now(),
        ]);

        return redirect()->route('share.dashboard', $token);
    }

    public function dashboard(Request $request, string $token)
    {
        $link = $request->attributes->get('shareLink');

        return view('share.dashboard', ['patient' => $link->patient, 'token' => $token, 'expiresAt' => $link->expires_at]);
    }

    public function history(Request $request, string $token)
    {
        $link = $request->attributes->get('shareLink');

        return view('share.history', ['patient' => $link->patient, 'token' => $token, 'expiresAt' => $link->expires_at]);
    }
}
