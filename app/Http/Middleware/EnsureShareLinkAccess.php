<?php

namespace App\Http\Middleware;

use App\Models\ShareLink;
use App\Services\ShareAccessSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege as rotas de leitura do link compartilhado (dashboard/histórico):
 * exige que o token exista, não esteja expirado, e que o PIN já tenha sido
 * verificado nesta sessão do navegador e ainda esteja no prazo (ver
 * ShareAccessSession) — docs/13.
 */
class EnsureShareLinkAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        $link = ShareLink::with('patient')->where('token', $token)->first();

        if (! $link || $link->isExpired()) {
            return redirect()->route('share.pin', $token)
                ->with('status', 'Este link é inválido ou expirou. Peça um novo à pessoa que compartilhou com você.');
        }

        if (! app(ShareAccessSession::class)->isActive($token)) {
            return redirect()->route('share.pin', $token);
        }

        $request->attributes->set('shareLink', $link);

        $response = $next($request);

        // Sem cache nem back/forward cache: depois de expirar/revogar, o botão
        // "voltar" não pode reexibir os dados a partir de uma cópia local.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
