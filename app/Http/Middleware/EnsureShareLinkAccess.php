<?php

namespace App\Http\Middleware;

use App\Models\ShareLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege as rotas de leitura do link compartilhado (dashboard/histórico):
 * exige que o token exista, não esteja expirado, e que o PIN já tenha sido
 * verificado nesta sessão do navegador (ver ShareAccessController) — docs/13.
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

        if (! session()->get("share_access.{$token}")) {
            return redirect()->route('share.pin', $token);
        }

        $request->attributes->set('shareLink', $link);

        return $next($request);
    }
}
