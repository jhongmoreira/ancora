<?php

namespace App\Services\Insights;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo da Gemini API (generateContent com saída JSON estruturada) —
 * docs/15, seção 2. HTTP direto, sem SDK.
 */
class GeminiClient
{
    protected const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /**
     * Status que valem uma nova tentativa (e, esgotadas as tentativas, o
     * modelo de fallback): cota/limite por minuto e indisponibilidade.
     */
    protected const RETRYABLE = [429, 500, 502, 503, 504];

    /**
     * Registros de compulsão sexual e de sofrimento emocional podem esbarrar
     * nos filtros padrão; bloqueamos só o que for classificado como alto risco.
     */
    protected const SAFETY_CATEGORIES = [
        'HARM_CATEGORY_HARASSMENT',
        'HARM_CATEGORY_HATE_SPEECH',
        'HARM_CATEGORY_SEXUALLY_EXPLICIT',
        'HARM_CATEGORY_DANGEROUS_CONTENT',
    ];

    /**
     * @return array{data: array, model: string, usage: array}
     */
    public function generateJson(string $systemPrompt, string $userContent, array $schema): array
    {
        $key = config('services.gemini.key');

        if (! $key) {
            throw new GeminiException('A integração com a IA não está configurada.', 'GEMINI_API_KEY ausente');
        }

        $models = array_values(array_unique(array_filter([
            config('services.gemini.model'),
            config('services.gemini.fallback_model'),
        ])));

        $lastError = null;

        foreach ($models as $model) {
            try {
                $response = $this->request($key, $model, $systemPrompt, $userContent, $schema);
            } catch (ConnectionException $e) {
                $lastError = new GeminiException('A IA demorou demais para responder. Tente novamente em alguns minutos.', $e->getMessage());

                continue;
            }

            if (in_array($response->status(), self::RETRYABLE, true)) {
                $lastError = new GeminiException(
                    $response->status() === 429
                        ? 'O limite gratuito de uso da IA foi atingido. Tente novamente mais tarde.'
                        : 'O serviço de IA está indisponível no momento. Tente novamente em alguns minutos.',
                    "HTTP {$response->status()} ({$model}): ".$response->body()
                );

                continue;
            }

            return ['data' => $this->parse($response, $model), 'model' => $model, 'usage' => $response->json('usageMetadata', [])];
        }

        throw $lastError;
    }

    protected function request(string $key, string $model, string $systemPrompt, string $userContent, array $schema): Response
    {
        return Http::withHeaders(['x-goog-api-key' => $key])
            ->timeout(config('services.gemini.timeout', 60))
            ->retry(
                2,
                fn (int $attempt) => $attempt * 2000,
                fn ($exception) => $exception instanceof ConnectionException
                    || in_array($exception->response?->status(), self::RETRYABLE, true),
                throw: false,
            )
            ->post(sprintf(self::ENDPOINT, $model), [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $userContent]]]],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                ],
                'safetySettings' => array_map(
                    fn ($category) => ['category' => $category, 'threshold' => 'BLOCK_ONLY_HIGH'],
                    self::SAFETY_CATEGORIES,
                ),
            ]);
    }

    protected function parse(Response $response, string $model): array
    {
        if ($response->failed()) {
            throw new GeminiException('Não foi possível gerar o relatório agora.', "HTTP {$response->status()} ({$model}): ".$response->body());
        }

        if ($reason = $response->json('promptFeedback.blockReason')) {
            throw new GeminiException('A IA recusou analisar estes registros. Tente novamente mais tarde.', "Prompt bloqueado: {$reason}");
        }

        $finishReason = $response->json('candidates.0.finishReason');
        $text = $response->json('candidates.0.content.parts.0.text');

        if ($finishReason !== 'STOP' || ! is_string($text)) {
            throw new GeminiException('A IA não conseguiu concluir o relatório. Tente novamente.', "finishReason: {$finishReason}");
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new GeminiException('A resposta da IA veio num formato inesperado. Tente novamente.', 'JSON inválido: '.mb_substr($text, 0, 500));
        }

        return $data;
    }
}
