<?php

namespace App\Services\Insights;

use RuntimeException;

/**
 * O relatório não pode ser gerado agora (sem consentimento, poucos
 * registros, limite de uso). Nada é enviado à IA nem salvo; a mensagem é
 * mostrada ao paciente.
 */
class InsightUnavailableException extends RuntimeException {}
