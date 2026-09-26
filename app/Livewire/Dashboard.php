<?php

namespace App\Livewire;

use App\Livewire\Concerns\GuardsSharedAccess;
use App\Models\MoodCategory;
use App\Models\Patient;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Dashboard extends Component
{
    use GuardsSharedAccess;

    protected const COLORS = [
        'green' => '#16a34a',
        'gray' => '#6b7280',
        'red' => '#dc2626',
    ];

    public ?Patient $patient = null;

    #[Locked]
    public bool $readOnly = false;

    public string $period = '15d';

    public ?string $from = null;

    public ?string $to = null;

    /**
     * @param  Patient|null  $patient  Quando omitido, usa o paciente do usuário
     *                                 autenticado (uso normal). Passado explicitamente
     *                                 (com $readOnly=true) na visão compartilhada com a
     *                                 psicóloga (ver ShareLink).
     */
    public function mount(?Patient $patient = null, bool $readOnly = false, ?string $shareToken = null): void
    {
        $this->patient = $patient ?? Auth::user()?->patient;
        $this->readOnly = $readOnly;
        $this->shareToken = $shareToken;
        $this->applyPeriodPreset($this->period);
    }

    public function updatedPeriod(string $value): void
    {
        $this->applyPeriodPreset($value);
        $this->dispatch('dashboard-updated', data: $this->chartData());
    }

    protected function applyPeriodPreset(string $period): void
    {
        $this->from = match ($period) {
            '7d' => now()->subDays(6)->toDateString(),
            '15d' => now()->subDays(14)->toDateString(),
            'month' => now()->startOfMonth()->toDateString(),
            default => now()->subDays(29)->toDateString(),
        };

        $this->to = now()->toDateString();
    }

    #[Computed]
    public function moodCategories()
    {
        return MoodCategory::orderBy('order')->get();
    }

    /**
     * @var \Illuminate\Support\Collection<int, \App\Models\EmotionLog>|null
     */
    protected ?\Illuminate\Support\Collection $logsCache = null;

    /**
     * Memoizado por requisição (não é propriedade pública do Livewire, então
     * não sobrevive entre requests) — várias seções do dashboard (gráficos,
     * consistência, heatmap, gatilhos, resumo) consultam o mesmo período.
     */
    protected function logsInPeriod()
    {
        return $this->logsCache ??= $this->patient
            ->emotionLogs()
            ->with('feelings', 'moodCategory')
            ->whereDate('occurred_at', '>=', $this->from)
            ->whereDate('occurred_at', '<=', $this->to)
            ->get();
    }

    public function chartData(): array
    {
        $logs = $this->logsInPeriod();

        $days = collect(CarbonPeriod::create($this->from, $this->to))
            ->map(fn (Carbon $date) => $date->toDateString());

        $moodCategories = $this->moodCategories;

        $moodSeries = $moodCategories->map(function ($category) use ($logs, $days) {
            $countsByDay = $logs->where('mood_category_id', $category->id)
                ->groupBy(fn ($log) => $log->occurred_at->toDateString())
                ->map->count();

            return [
                'label' => $category->label,
                'color' => self::COLORS[$category->color] ?? '#6b7280',
                'data' => $days->map(fn ($day) => $countsByDay->get($day, 0))->values(),
            ];
        })->values();

        $feelingCounts = $logs->flatMap->feelings
            ->groupBy('name')
            ->map->count()
            ->sortDesc()
            ->take(8);

        $distribution = $moodCategories->map(
            fn ($category) => $logs->where('mood_category_id', $category->id)->count()
        );

        return [
            'labels' => $days->map(fn ($day) => Carbon::parse($day)->format('d/m'))->values(),
            'moodSeries' => $moodSeries,
            'feelingLabels' => $feelingCounts->keys()->values(),
            'feelingCounts' => $feelingCounts->values(),
            'distributionLabels' => $moodCategories->pluck('label')->values(),
            'distributionColors' => $moodCategories->pluck('color')->map(fn ($c) => self::COLORS[$c] ?? '#6b7280')->values(),
            'distribution' => $distribution->values(),
            'total' => $logs->count(),
        ];
    }

    /**
     * "Quantos dos dias do período você registrou pelo menos uma vez" —
     * mostra adesão ao diário, informação que a psicóloga costuma querer
     * antes de interpretar o resto dos gráficos.
     */
    public function consistencyData(): array
    {
        $logs = $this->logsInPeriod();

        $daysWithLogs = $logs->map(fn ($log) => $log->occurred_at->toDateString())->unique()->count();
        $totalDays = (int) Carbon::parse($this->from)->diffInDays(Carbon::parse($this->to)) + 1;

        return [
            'daysWithLogs' => $daysWithLogs,
            'totalDays' => $totalDays,
            'percentage' => $totalDays > 0 ? (int) round(($daysWithLogs / $totalDays) * 100) : 0,
        ];
    }

    /**
     * Sequência atual de dias seguidos com pelo menos um registro — olha
     * todo o histórico do paciente (não só o período do filtro), já que
     * "streak" é uma medida contínua, não recortada por período. Se hoje
     * ainda não tem registro, começa a contar de ontem (o dia não acabou,
     * então a sequência não é considerada quebrada ainda).
     */
    public function streakData(): array
    {
        $logDates = $this->patient
            ->emotionLogs()
            ->pluck('occurred_at')
            ->map(fn ($dt) => $dt->toDateString())
            ->unique();

        $cursor = today();

        if (! $logDates->contains($cursor->toDateString())) {
            $cursor = $cursor->copy()->subDay();
        }

        $streak = 0;

        while ($logDates->contains($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->copy()->subDay();
        }

        return ['current' => $streak];
    }

    protected const PERIODS_OF_DAY = [
        'Manhã' => [0, 11],
        'Tarde' => [12, 17],
        'Noite' => [18, 23],
    ];

    protected const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    /**
     * Concentração de registros de humor DESAGRADÁVEL (key `negativo`) por dia da semana x
     * período do dia — ajuda a identificar gatilhos recorrentes (ex.:
     * "segundas de manhã"), o tipo de padrão que vira assunto de sessão.
     */
    public function heatmapData(): array
    {
        $logs = $this->logsInPeriod()->filter(
            fn ($log) => $log->moodCategory->key === 'negativo'
        );

        $grid = [];

        foreach (self::WEEKDAYS as $day) {
            foreach (array_keys(self::PERIODS_OF_DAY) as $period) {
                $grid[$day][$period] = 0;
            }
        }

        foreach ($logs as $log) {
            $day = self::WEEKDAYS[$log->occurred_at->dayOfWeek];
            $hour = $log->occurred_at->hour;

            foreach (self::PERIODS_OF_DAY as $period => [$start, $end]) {
                if ($hour >= $start && $hour <= $end) {
                    $grid[$day][$period]++;

                    break;
                }
            }
        }

        $max = collect($grid)->flatten()->max() ?: 1;

        return [
            'grid' => $grid,
            'periods' => array_keys(self::PERIODS_OF_DAY),
            'days' => self::WEEKDAYS,
            'max' => $max,
            'total' => $logs->count(),
        ];
    }

    /**
     * Palavras funcionais (stopwords) em pt-BR ignoradas ao contar termos
     * recorrentes nas situações — lista prática, não é um dicionário
     * linguístico completo.
     */
    protected const STOPWORDS = [
        'a', 'o', 'as', 'os', 'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas',
        'um', 'uma', 'uns', 'umas', 'para', 'por', 'com', 'sem', 'sobre', 'entre', 'até',
        'que', 'quando', 'como', 'mas', 'ou', 'e', 'se', 'não', 'mais', 'muito', 'pouco',
        'já', 'só', 'também', 'ainda', 'eu', 'me', 'meu', 'minha', 'meus', 'minhas',
        'ele', 'ela', 'eles', 'elas', 'seu', 'sua', 'seus', 'suas', 'nosso', 'nossa',
        'isso', 'isto', 'aquilo', 'este', 'esta', 'esse', 'essa', 'aquele', 'aquela',
        'ser', 'estar', 'ter', 'foi', 'era', 'sou', 'é', 'são', 'está', 'estava', 'estou',
        'fui', 'tinha', 'tive', 'teve', 'vou', 'vai', 'fazer', 'fiz', 'dia', 'dias', 'hoje',
        'depois', 'antes', 'durante', 'pela', 'pelo', 'pelas', 'pelos', 'ao', 'aos', 'à', 'às',
    ];

    /**
     * Palavras mais recorrentes nas situações associadas a humor DESAGRADÁVEL —
     * uma aproximação simples (contagem de palavras, sem NLP) de "gatilhos"
     * recorrentes, que é o tipo de padrão que a literatura de TCC destaca
     * como mais útil pra sessão (docs — pesquisa "Mood Charts in Therapy").
     */
    public function triggersData(): array
    {
        $logs = $this->logsInPeriod()->filter(
            fn ($log) => $log->moodCategory->key === 'negativo'
        );

        $wordCounts = [];

        foreach ($logs as $log) {
            $words = preg_split('/[^\p{L}]+/u', mb_strtolower($log->situation)) ?: [];

            foreach (array_unique($words) as $word) {
                if (mb_strlen($word) <= 2 || in_array($word, self::STOPWORDS, true)) {
                    continue;
                }

                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
            }
        }

        arsort($wordCounts);

        return [
            'words' => array_slice($wordCounts, 0, 8, true),
            'total' => $logs->count(),
        ];
    }

    protected const WEEKDAY_PHRASES = [
        'Dom' => 'domingos',
        'Seg' => 'segundas',
        'Ter' => 'terças',
        'Qua' => 'quartas',
        'Qui' => 'quintas',
        'Sex' => 'sextas',
        'Sáb' => 'sábados',
    ];

    protected const PERIOD_PHRASES = [
        'Manhã' => 'de manhã',
        'Tarde' => 'à tarde',
        'Noite' => 'à noite',
    ];

    /**
     * Frase-resumo gerada a partir dos agregados já calculados — pensada
     * pra psicóloga ler em poucos segundos antes de abrir o resto do
     * dashboard (mesma ideia dos PDFs de apps como eMoods/Moodfit).
     */
    public function summaryText(): string
    {
        $logs = $this->logsInPeriod();

        if ($logs->isEmpty()) {
            return 'Nenhum registro no período selecionado.';
        }

        $moodCounts = $logs->groupBy('mood_category_id')->map->count();
        $dominantMoodId = $moodCounts->sortDesc()->keys()->first();
        $dominantMood = $this->moodCategories->firstWhere('id', $dominantMoodId);
        $dominantPercentage = (int) round(($moodCounts[$dominantMoodId] / $logs->count()) * 100);

        $topFeeling = $logs->flatMap->feelings->groupBy('name')->map->count()->sortDesc()->keys()->first();

        $heatmap = $this->heatmapData();
        $peakDay = null;
        $peakPeriod = null;
        $peakCount = 0;

        foreach ($heatmap['grid'] as $day => $periods) {
            foreach ($periods as $period => $count) {
                if ($count > $peakCount) {
                    [$peakCount, $peakDay, $peakPeriod] = [$count, $day, $period];
                }
            }
        }

        $parts = [];
        $parts[] = $logs->count().' '.Str::plural('registro', $logs->count());
        $parts[] = 'predominantemente humor '.mb_strtolower($dominantMood->label)." ({$dominantPercentage}%)";

        if ($topFeeling) {
            $parts[] = "sentimento mais comum \"{$topFeeling}\"";
        }

        if ($peakCount > 0) {
            $dayPhrase = self::WEEKDAY_PHRASES[$peakDay] ?? $peakDay;
            $periodPhrase = self::PERIOD_PHRASES[$peakPeriod] ?? $peakPeriod;
            $parts[] = "humor desagradável concentrado às {$dayPhrase} {$periodPhrase}";
        }

        $sentences = [ucfirst(implode(', ', $parts)).'.'];

        $withIntensity = $logs->whereNotNull('intensity');

        if ($withIntensity->isNotEmpty()) {
            $avgIntensity = round($withIntensity->avg('intensity'), 1);
            $sentences[] = "Intensidade média informada: {$avgIntensity}/5 (em {$withIntensity->count()} de {$logs->count()} registros).";
        }

        return implode(' ', $sentences);
    }

    /**
     * Tempo médio entre um registro de humor DESAGRADÁVEL e o próximo registro
     * agradável ou neutro — uma aproximação de "velocidade de recuperação
     * emocional", conceito central em terapia de regulação emocional/DBT.
     *
     * Olha além do `to` do período pra achar o "próximo" registro corretamente
     * mesmo quando ele cai logo depois da borda do filtro (evita subestimar
     * a recuperação de episódios desagradáveis perto do fim do período).
     */
    public function recoveryData(): array
    {
        $negativeId = $this->moodCategories->firstWhere('key', 'negativo')?->id;
        $positiveOrNeutralIds = $this->moodCategories->whereIn('key', ['positivo', 'neutro'])->pluck('id')->all();

        $negativeLogs = $this->logsInPeriod()
            ->where('mood_category_id', $negativeId)
            ->sortBy('occurred_at')
            ->values();

        if ($negativeLogs->isEmpty()) {
            return ['average' => null, 'count' => 0, 'analyzed' => 0];
        }

        $futureLogs = $this->patient->emotionLogs()
            ->where('occurred_at', '>=', $negativeLogs->first()->occurred_at)
            ->orderBy('occurred_at')
            ->get(['id', 'mood_category_id', 'occurred_at']);

        $recoveryHours = [];

        foreach ($negativeLogs as $negativeLog) {
            $next = $futureLogs->first(
                fn ($log) => $log->occurred_at->gt($negativeLog->occurred_at)
                    && in_array($log->mood_category_id, $positiveOrNeutralIds, true)
            );

            if ($next) {
                $recoveryHours[] = $negativeLog->occurred_at->diffInMinutes($next->occurred_at) / 60;
            }
        }

        if (empty($recoveryHours)) {
            return ['average' => null, 'count' => 0, 'analyzed' => $negativeLogs->count()];
        }

        return [
            'average' => round(array_sum($recoveryHours) / count($recoveryHours), 1),
            'count' => count($recoveryHours),
            'analyzed' => $negativeLogs->count(),
        ];
    }

    /**
     * Formata horas fracionárias em algo legível ("3h", "1 dia e 4h", "2 dias").
     */
    public function formatRecoveryDuration(float $hours): string
    {
        if ($hours < 24) {
            return round($hours).'h';
        }

        $days = intdiv((int) round($hours), 24);
        $remainingHours = (int) round($hours) % 24;

        $label = $days.' '.Str::plural('dia', $days);

        return $remainingHours > 0 ? "{$label} e {$remainingHours}h" : $label;
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'chartData' => $this->chartData(),
            'consistency' => $this->consistencyData(),
            'streak' => $this->streakData(),
            'heatmap' => $this->heatmapData(),
            'triggers' => $this->triggersData(),
            'summary' => $this->summaryText(),
            'recovery' => $this->recoveryData(),
        ]);
    }
}
