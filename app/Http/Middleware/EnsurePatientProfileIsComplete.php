<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onboarding simples (docs/05, seção 5): enquanto o paciente não tiver ao
 * menos nome e data de nascimento preenchidos, as rotas do app (dashboard,
 * registro emocional, histórico, relatórios...) redirecionam para "Meus
 * dados". Aplicado apenas às rotas do domínio do app (alias `onboarded`),
 * nunca globalmente, para não interferir nas rotas de auth do Breeze.
 */
class EnsurePatientProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $patient = $user ? ($user->patient ?? $user->patient()->create()) : null;

        if ($patient && ! $patient->isProfileComplete() && ! $request->routeIs('patient.edit')) {
            return redirect()->route('patient.edit')
                ->with('status', 'Complete seu cadastro antes de começar a registrar.');
        }

        return $next($request);
    }
}
