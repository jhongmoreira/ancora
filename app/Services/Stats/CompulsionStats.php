<?php

namespace App\Services\Stats;

use App\Models\CompulsionLog;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Estatísticas do mapa de compulsões num período — usadas pelo mapa
 * (App\Livewire\Compulsion\History) e pelos insights com IA (docs/15).
 */
class CompulsionStats
{
    public const TIME_BUCKETS = [
        'madrugada' => 'Madrugada (0h–6h)',
        'manha' => 'Manhã (6h–12h)',
        'tarde' => 'Tarde (12h–18h)',
        'noite' => 'Noite (18h–24h)',
    ];

    public const WEEKDAYS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

    protected ?Collection $logsCache = null;

    protected ?Collection $compulsionsCache = null;

    public function __construct(
        protected Patient $patient,
        protected ?string $from,
        protected ?string $to,
        protected ?int $compulsionId = null,
    ) {}

    public function query()
    {
        return $this->patient
            ->compulsionLogs()
            ->when($this->from, fn ($q) => $q->whereDate('occurred_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('occurred_at', '<=', $this->to))
            ->when($this->compulsionId, fn ($q) => $q->where('compulsion_id', $this->compulsionId));
    }

    public function compulsions(): Collection
    {
        return $this->compulsionsCache ??= $this->patient->compulsions()->orderBy('name')->get();
    }

    /** Todos os registros do período (sem paginação), base do resumo e dos padrões. */
    public function logs(): Collection
    {
        return $this->logsCache ??= $this->query()->with('feelings')->get();
    }

    public function summary(): Collection
    {
        $lastGaveIn = $this->patient->compulsionLogs()
            ->where('outcome', CompulsionLog::OUTCOME_GAVE_IN)
            ->selectRaw('compulsion_id, max(occurred_at) as last_at')
            ->groupBy('compulsion_id')
            ->pluck('last_at', 'compulsion_id');

        return $this->compulsions()
            ->filter(fn ($c) => ! $this->compulsionId || $c->id === $this->compulsionId)
            ->map(function ($compulsion) use ($lastGaveIn) {
                $logs = $this->logs()->where('compulsion_id', $compulsion->id);
                $last = $lastGaveIn->get($compulsion->id);

                return [
                    'compulsion' => $compulsion,
                    'gave_in' => $logs->where('outcome', CompulsionLog::OUTCOME_GAVE_IN)->count(),
                    'resisted' => $logs->where('outcome', CompulsionLog::OUTCOME_RESISTED)->count(),
                    'avg_urge' => $logs->isEmpty() ? null : round($logs->avg('urge_intensity'), 1),
                    'days_since_gave_in' => $last ? (int) Carbon::parse($last)->startOfDay()->diffInDays(now()->startOfDay()) : null,
                ];
            })
            // Arquivadas sem registros no período não poluem o resumo.
            ->reject(fn ($row) => $row['compulsion']->isArchived() && $row['gave_in'] + $row['resisted'] === 0)
            ->values();
    }

    /**
     * Sentimentos mais frequentes num momento (antes/depois), opcionalmente
     * restritos a um desfecho.
     *
     * @return Collection<int, array{name: string, count: int}>
     */
    public function topFeelings(string $moment, ?string $outcome = null, int $limit = 5): Collection
    {
        return $this->logs()
            ->when($outcome, fn ($logs) => $logs->where('outcome', $outcome))
            ->flatMap(fn ($log) => $log->feelings->where('pivot.moment', $moment))
            ->countBy('name')
            ->sortDesc()
            ->take($limit)
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
            ->values();
    }

    public function patterns(): array
    {
        $byTime = collect(self::TIME_BUCKETS)->map(fn ($label) => [
            'label' => $label,
            'gave_in' => 0,
            'resisted' => 0,
        ])->all();

        $byWeekday = collect(self::WEEKDAYS)->map(fn ($label) => [
            'label' => $label,
            'gave_in' => 0,
            'resisted' => 0,
        ])->all();

        foreach ($this->logs() as $log) {
            $byTime[self::timeBucket($log->occurred_at->hour)][$log->outcome]++;
            $byWeekday[$log->occurred_at->isoWeekday()][$log->outcome]++;
        }

        return [
            'before' => $this->topFeelings(CompulsionLog::MOMENT_BEFORE),
            'after_gave_in' => $this->topFeelings(CompulsionLog::MOMENT_AFTER, CompulsionLog::OUTCOME_GAVE_IN),
            'after_resisted' => $this->topFeelings(CompulsionLog::MOMENT_AFTER, CompulsionLog::OUTCOME_RESISTED),
            'by_time' => $byTime,
            'by_weekday' => $byWeekday,
            'max_time' => max(1, ...array_values(array_map(fn ($r) => $r['gave_in'] + $r['resisted'], $byTime))),
            'max_weekday' => max(1, ...array_values(array_map(fn ($r) => $r['gave_in'] + $r['resisted'], $byWeekday))),
        ];
    }

    public static function timeBucket(int $hour): string
    {
        return match (true) {
            $hour < 6 => 'madrugada',
            $hour < 12 => 'manha',
            $hour < 18 => 'tarde',
            default => 'noite',
        };
    }
}
