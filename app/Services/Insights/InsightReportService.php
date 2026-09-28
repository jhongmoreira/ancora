<?php

namespace App\Services\Insights;

use App\Models\InsightReport;
use App\Models\Patient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Gera e salva o relatório de insights de um período (docs/15).
 */
class InsightReportService
{
    public const PROMPT_VERSION = '2';

    public function __construct(
        protected InsightDataBuilder $builder,
        protected GeminiClient $gemini,
    ) {}

    /**
     * Gera um relatório novo. Retorna o relatório salvo, `completed` ou
     * `failed` (quando a IA falha, o erro fica registrado nele).
     *
     * @throws InsightUnavailableException quando não dá para gerar agora
     */
    public function generate(
        Patient $patient,
        string $preset = InsightDataBuilder::DEFAULT_PERIOD,
        ?string $from = null,
        ?string $to = null,
    ): InsightReport {
        if (! $patient->hasAiConsent()) {
            throw new InsightUnavailableException('Antes de gerar, é preciso aceitar os termos de uso da IA.');
        }

        if (! array_key_exists($preset, InsightDataBuilder::PERIODS)) {
            $preset = InsightDataBuilder::DEFAULT_PERIOD;
        }

        ['period_start' => $from, 'period_end' => $to] = InsightDataBuilder::resolvePeriod($preset, $from, $to);

        $data = $this->builder->build($patient, $from, $to);

        if ($data['total_records'] < InsightDataBuilder::MIN_RECORDS) {
            throw new InsightUnavailableException(
                'Registre um pouco mais para gerar insights: são necessários pelo menos '.InsightDataBuilder::MIN_RECORDS.' registros no período escolhido.'
            );
        }

        $this->hitRateLimits($patient);

        // A chamada à IA pode levar dezenas de segundos (docs/15, seção 10).
        @set_time_limit(180);

        $report = new InsightReport([
            'period_preset' => $preset,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'prompt_version' => self::PROMPT_VERSION,
            'stats' => $data['stats'],
            'payload' => $data['payload'],
            'risk_flag' => $data['risk_record_ids'] !== [],
            'risk_record_ids' => $data['risk_record_ids'],
        ]);

        try {
            $result = $this->gemini->generateJson(
                self::prompt(),
                "<registros>\n".json_encode($data['payload'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n</registros>",
                InsightSchema::schema(),
            );

            $report->fill([
                'status' => InsightReport::STATUS_COMPLETED,
                'model' => $result['model'],
                'usage' => $result['usage'],
                'content' => InsightSchema::validate($result['data'], $data['record_ids']),
            ]);
        } catch (GeminiException $e) {
            Log::warning('Falha ao gerar insights', ['patient_id' => $patient->id, 'detail' => $e->detail]);

            $report->fill([
                'status' => InsightReport::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        }

        $patient->insightReports()->save($report);

        return $report;
    }

    public static function prompt(): string
    {
        return file_get_contents(resource_path('prompts/insights.md'));
    }

    /**
     * Protege a cota gratuita e evita cliques repetidos: 1 geração a cada
     * `insights.cooldown_seconds` e até `insights.max_reports_per_day` por
     * dia (config/insights.php, configurável via .env).
     */
    protected function hitRateLimits(Patient $patient): void
    {
        $cooldownKey = "insights:cooldown:{$patient->id}";
        $dailyKey = "insights:daily:{$patient->id}";
        $maxPerDay = config('insights.max_reports_per_day');

        if (RateLimiter::tooManyAttempts($dailyKey, $maxPerDay)) {
            throw new InsightUnavailableException("Você atingiu o limite de {$maxPerDay} relatórios por dia. Tente novamente amanhã.");
        }

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $minutes = (int) ceil(RateLimiter::availableIn($cooldownKey) / 60);

            throw new InsightUnavailableException("Aguarde {$minutes} ".($minutes === 1 ? 'minuto' : 'minutos').' para gerar um novo relatório.');
        }

        RateLimiter::hit($cooldownKey, config('insights.cooldown_seconds'));
        RateLimiter::hit($dailyKey, 86400);
    }
}
