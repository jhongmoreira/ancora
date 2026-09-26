<?php

namespace App\Services\Insights;

use App\Models\CompulsionLog;
use App\Models\EmotionLog;
use App\Models\Patient;
use App\Services\Stats\CompulsionStats;
use App\Services\Stats\EmotionStats;
use Illuminate\Support\Carbon;

/**
 * Monta os dados do PERÍODO ESCOLHIDO para o relatório de insights (docs/15,
 * seção 3):
 * - `stats`: números calculados no app, exibidos na tela (nunca vindos da IA);
 * - `payload`: pacote anonimizado enviado ao Gemini;
 * - `record_ids`: ids válidos, para validar as evidências citadas pela IA;
 * - `risk_record_ids`: registros com conteúdo de risco (RiskDetector).
 */
class InsightDataBuilder
{
    /** Mesmas opções do mapa de compulsões; a quinzena é o padrão. */
    public const PERIODS = [
        '7d' => 'Últimos 7 dias',
        '15d' => 'Última quinzena',
        '30d' => 'Últimos 30 dias',
        '90d' => 'Últimos 90 dias',
        'month' => 'Este mês',
        'custom' => 'Personalizado',
    ];

    public const DEFAULT_PERIOD = '15d';

    /** Limite do período personalizado: mantém o relatório focado e o envio pequeno. */
    public const MAX_DAYS = 90;

    public const MIN_RECORDS = 3;

    protected const WEEKDAYS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    public function __construct(protected RiskDetector $riskDetector) {}

    /**
     * Resolve a opção do seletor em datas. No personalizado, valida as
     * datas informadas.
     *
     * @return array{period_start: string, period_end: string}
     *
     * @throws InsightUnavailableException
     */
    public static function resolvePeriod(string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = today();

        if ($preset !== 'custom') {
            $start = match ($preset) {
                '7d' => $today->copy()->subDays(6),
                '30d' => $today->copy()->subDays(29),
                '90d' => $today->copy()->subDays(89),
                'month' => $today->copy()->startOfMonth(),
                default => $today->copy()->subDays(14),
            };

            return ['period_start' => $start->toDateString(), 'period_end' => $today->toDateString()];
        }

        try {
            $start = Carbon::createFromFormat('!Y-m-d', (string) $from);
            $end = Carbon::createFromFormat('!Y-m-d', (string) $to);
        } catch (\Throwable) {
            throw new InsightUnavailableException('Informe as datas de início e fim do período.');
        }

        if ($start->gt($end)) {
            throw new InsightUnavailableException('A data de início precisa ser anterior à data de fim.');
        }

        if ($end->gt($today)) {
            throw new InsightUnavailableException('O período não pode terminar no futuro.');
        }

        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw new InsightUnavailableException('Escolha um período de até '.self::MAX_DAYS.' dias.');
        }

        return ['period_start' => $start->toDateString(), 'period_end' => $end->toDateString()];
    }

    public function build(Patient $patient, string $from, string $to): array
    {

        $emotion = new EmotionStats($patient, $from, $to);
        $compulsion = new CompulsionStats($patient, $from, $to);

        $emotionLogs = $emotion->logs()->sortBy('occurred_at')->values();
        $compulsionLogs = $compulsion->logs()->load('compulsion')->sortBy('occurred_at')->values();

        $anonymizer = new InsightAnonymizer([$patient->full_name, $patient->professional?->name]);
        $start = Carbon::parse($from);

        $records = [
            ...$emotionLogs->map(fn (EmotionLog $log) => $this->emotionRecord($log, $start, $anonymizer)),
            ...$compulsionLogs->map(fn (CompulsionLog $log) => $this->compulsionRecord($log, $start, $anonymizer)),
        ];

        $stats = $this->stats($emotion, $compulsion, $from, $to);

        return [
            'period_start' => $from,
            'period_end' => $to,
            'total_records' => count($records),
            'stats' => $stats,
            'payload' => [
                'periodo' => [
                    'dias' => (int) $start->diffInDays(Carbon::parse($to)) + 1,
                    'inicio_dia_semana' => self::WEEKDAYS[$start->dayOfWeek],
                ],
                'estatisticas' => $this->statsForPrompt($stats),
                'registros' => $records,
            ],
            'record_ids' => array_column($records, 'id'),
            'risk_record_ids' => $this->riskDetector->detect([
                ...$emotionLogs->mapWithKeys(fn ($log) => ["E{$log->id}" => [$log->situation, $log->action, $log->automatic_thought]])->all(),
                ...$compulsionLogs->mapWithKeys(fn ($log) => ["C{$log->id}" => [$log->trigger, $log->automatic_thought, $log->coping_strategy, $log->notes]])->all(),
            ]),
        ];
    }

    protected function when(Carbon $at, Carbon $start): array
    {
        return [
            'dia' => (int) $start->diffInDays($at->copy()->startOfDay()) + 1,
            'dia_semana' => self::WEEKDAYS[$at->dayOfWeek],
            'hora' => $at->format('H\hi'),
        ];
    }

    protected function emotionRecord(EmotionLog $log, Carbon $start, InsightAnonymizer $anonymizer): array
    {
        return array_filter([
            'id' => "E{$log->id}",
            'tipo' => 'registro_emocional',
            ...$this->when($log->occurred_at, $start),
            'humor' => $log->moodCategory->label,
            'intensidade_1a5' => $log->intensity,
            'sentimentos' => $log->feelings->pluck('name')->values()->all(),
            'situacao' => $anonymizer->anonymize($log->situation),
            'acao' => $anonymizer->anonymize($log->action),
            'pensamento_automatico' => $anonymizer->anonymize($log->automatic_thought),
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected function compulsionRecord(CompulsionLog $log, Carbon $start, InsightAnonymizer $anonymizer): array
    {
        return array_filter([
            'id' => "C{$log->id}",
            'tipo' => 'compulsao',
            ...$this->when($log->occurred_at, $start),
            'compulsao' => $log->compulsion->name,
            'desfecho' => $log->gaveIn() ? 'cedeu' : 'resistiu',
            'vontade_0a10' => $log->urge_intensity,
            'gatilho' => $anonymizer->anonymize($log->trigger),
            'pensamento_automatico' => $anonymizer->anonymize($log->automatic_thought),
            'sentimentos_antes' => $log->feelings->where('pivot.moment', CompulsionLog::MOMENT_BEFORE)->pluck('name')->values()->all(),
            'sentimentos_depois' => $log->feelings->where('pivot.moment', CompulsionLog::MOMENT_AFTER)->pluck('name')->values()->all(),
            'duracao_min' => $log->duration_minutes,
            'estrategia' => $anonymizer->anonymize($log->coping_strategy),
            'observacoes' => $anonymizer->anonymize($log->notes),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Números do período, calculados aqui e exibidos na tela do relatório.
     */
    protected function stats(EmotionStats $emotion, CompulsionStats $compulsion, string $from, string $to): array
    {
        $logs = $emotion->logs();
        $consistency = $emotion->consistency();
        $withIntensity = $logs->whereNotNull('intensity');
        $recovery = $emotion->recovery();

        $moods = $emotion->moodCategories()->map(fn ($category) => [
            'key' => $category->key,
            'label' => $category->label,
            'count' => $logs->where('mood_category_id', $category->id)->count(),
        ])->values()->all();

        $patterns = $compulsion->patterns();

        return [
            'emotion' => [
                'total' => $logs->count(),
                'days_with_logs' => $consistency['daysWithLogs'],
                'total_days' => $consistency['totalDays'],
                'moods' => $moods,
                'top_feelings' => $logs->flatMap->feelings->countBy('name')->sortDesc()->take(5)
                    ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])->values()->all(),
                'avg_intensity' => $withIntensity->isEmpty() ? null : round($withIntensity->avg('intensity'), 1),
                'recovery_hours' => $recovery['average'],
                'trigger_words' => array_keys($emotion->triggers()['words']),
            ],
            'compulsions' => $compulsion->summary()->map(fn ($row) => [
                'name' => $row['compulsion']->name,
                'gave_in' => $row['gave_in'],
                'resisted' => $row['resisted'],
                'avg_urge' => $row['avg_urge'],
            ])->filter(fn ($row) => $row['gave_in'] + $row['resisted'] > 0)->values()->all(),
            'compulsion_patterns' => [
                'before' => $patterns['before']->all(),
                'after_gave_in' => $patterns['after_gave_in']->all(),
                'after_resisted' => $patterns['after_resisted']->all(),
                'by_time' => collect($patterns['by_time'])->map(fn ($r) => $r['gave_in'] + $r['resisted'])->all(),
            ],
        ];
    }

    /**
     * Mesmos números, sem nada que identifique o paciente, em chaves em
     * português para o prompt.
     */
    protected function statsForPrompt(array $stats): array
    {
        $e = $stats['emotion'];

        return [
            'registros_emocionais' => $e['total'],
            'dias_com_registro' => "{$e['days_with_logs']} de {$e['total_days']}",
            'humor' => collect($e['moods'])->mapWithKeys(fn ($m) => [$m['label'] => $m['count']])->all(),
            'sentimentos_mais_frequentes' => collect($e['top_feelings'])->mapWithKeys(fn ($f) => [$f['name'] => $f['count']])->all(),
            'intensidade_media_1a5' => $e['avg_intensity'],
            'horas_medias_ate_recuperar_de_humor_desagradavel' => $e['recovery_hours'],
            'palavras_recorrentes_em_situacoes_desagradaveis' => $e['trigger_words'],
            'compulsoes' => collect($stats['compulsions'])->map(fn ($c) => [
                'compulsao' => $c['name'],
                'cedeu' => $c['gave_in'],
                'resistiu' => $c['resisted'],
                'vontade_media_0a10' => $c['avg_urge'],
            ])->all(),
            'impulsos_por_faixa_horaria' => $stats['compulsion_patterns']['by_time'],
        ];
    }
}
