<?php

namespace App\Services\Insights;

use RuntimeException;

/**
 * Falha ao gerar conteúdo no Gemini. A mensagem é pensada para ser mostrada
 * ao paciente (em português, sem detalhes técnicos); o detalhe técnico vai
 * em $detail, para o log e para o relatório `failed`.
 */
class GeminiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $detail = null)
    {
        parent::__construct($message);
    }
}
