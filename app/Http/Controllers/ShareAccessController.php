<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use App\Services\ShareAccessSession;
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

        if (app(ShareAccessSession::class)->isActive($token)) {
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
        app(ShareAccessSession::class)->grant($token);

        $link->patient->shareLinkAccesses()->create([
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'accessed_at' => now(),
        ]);

        return redirect()->route('share.dashboard', $token);
    }

    /**
     * Consultado periodicamente pela página compartilhada aberta (ver
     * ancoraShareGuard em resources/js/app.js): quando deixa de ser válido
     * (expirou, foi revogado/regenerado, o PIN não foi validado nesta sessão
     * ou o prazo do PIN acabou), a página apaga o conteúdo da tela e volta
     * para share.pin.
     */
    public function status(string $token)
    {
        $link = ShareLink::where('token', $token)->first();

        $access = app(ShareAccessSession::class);
        $valid = $link && ! $link->isExpired() && $access->isActive($token);

        return response()->json([
            'valid' => (bool) $valid,
            'remaining_seconds' => $valid ? max(0, (int) now()->diffInSeconds($link->expires_at)) : 0,
            'pin_remaining_seconds' => $valid ? $access->remainingSeconds($token) : 0,
        ])->header('Cache-Control', 'no-store, private');
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

    public function compulsions(Request $request, string $token)
    {
        $link = $request->attributes->get('shareLink');

        return view('share.compulsions', ['patient' => $link->patient, 'token' => $token, 'expiresAt' => $link->expires_at]);
    }

    public function insights(Request $request, string $token)
    {
        $link = $request->attributes->get('shareLink');

        return view('share.insights', ['patient' => $link->patient, 'token' => $token, 'expiresAt' => $link->expires_at]);
    }
}
