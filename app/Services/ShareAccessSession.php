<?php

namespace App\Services;

/**
 * Marca na sessão do navegador que o PIN de um link compartilhado foi
 * validado, guardando quando. O acesso vale por um tempo fixo a partir daí
 * (config('share.pin_validity_minutes')) — navegar ou recarregar a página não
 * prorroga; depois disso o PIN é pedido de novo — docs/13.
 */
class ShareAccessSession
{
    public function grant(string $token): void
    {
        session()->put($this->key($token), now()->getTimestamp());
    }

    /** Encerra o acesso agora (botão "Sair"). */
    public function end(string $token): void
    {
        session()->forget($this->key($token));
    }

    /** Diz se o PIN deste link foi validado nesta sessão e o prazo ainda não acabou. */
    public function isActive(string $token): bool
    {
        $grantedAt = session()->get($this->key($token));

        if (! $grantedAt) {
            return false;
        }

        // Sessões anteriores a este controle guardavam só `true`: tratadas como vencidas.
        if (! is_int($grantedAt) || now()->getTimestamp() - $grantedAt > $this->validitySeconds()) {
            $this->end($token);
            session()->flash('status', "Por segurança, o PIN vale por {$this->validityMinutes()} minutos. Digite-o novamente para continuar.");

            return false;
        }

        return true;
    }

    /** Segundos até o PIN precisar ser digitado de novo (0 se já venceu ou nunca foi validado). */
    public function remainingSeconds(string $token): int
    {
        $grantedAt = session()->get($this->key($token));

        if (! is_int($grantedAt)) {
            return 0;
        }

        return max(0, $this->validitySeconds() - (now()->getTimestamp() - $grantedAt));
    }

    public function validityMinutes(): int
    {
        return config('share.pin_validity_minutes');
    }

    protected function validitySeconds(): int
    {
        return $this->validityMinutes() * 60;
    }

    protected function key(string $token): string
    {
        return "share_access.{$token}";
    }
}
