<?php

namespace App\Livewire\Compulsion;

use App\Livewire\Concerns\GuardsSharedAccess;
use App\Models\CompulsionLog;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Mapa de compulsões (docs/14): resumo por compulsão, padrões do período
 * (sentimentos antes/depois, horário, dia da semana) e lista de registros.
 * Também usado na visão compartilhada com a psicóloga ($readOnly=true).
 */
class History extends Component
{
    use GuardsSharedAccess;
    use WithPagination;

    public const TIME_BUCKETS = [
        'madrugada' => 'Madrugada (0h–6h)',
        'manha' => 'Manhã (6h–12h)',
        'tarde' => 'Tarde (12h–18h)',
        'noite' => 'Noite (18h–24h)',
    ];

    public const WEEKDAYS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

    public ?Patient $patient = null;

    #[Locked]
    public bool $readOnly = false;

    #[Url]
    public string $period = '15d';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    /** Sem tipo declarado: uma URL malformada não pode travar a hidratação (ver EmotionLog\History). */
    #[Url]
    public $compulsion = null;

    public ?int $confirmingDeleteId = null;

    public function mount(?Patient $patient = null, bool $readOnly = false, ?string $shareToken = null): void
    {
        $this->patient = $patient ?? Auth::user()?->patient;
        $this->readOnly = $readOnly;
        $this->shareToken = $shareToken;
        $this->compulsion = is_numeric($this->compulsion) ? (int) $this->compulsion : null;
        $this->applyPeriodPreset($this->period);
    }

    public function updatedPeriod(string $value): void
    {
        $this->applyPeriodPreset($value);
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->period = 'custom';
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->period = 'custom';
        $this->resetPage();
    }

    public function updatedCompulsion($value): void
    {
        $this->compulsion = is_numeric($value) ? (int) $value : null;
        $this->resetPage();
    }

    protected function applyPeriodPreset(string $period): void
    {
        $this->from = match ($period) {
            '7d' => now()->subDays(6)->toDateString(),
            '15d' => now()->subDays(14)->toDateString(),
            '30d' => now()->subDays(29)->toDateString(),
            '90d' => now()->subDays(89)->toDateString(),
            'month' => now()->startOfMonth()->toDateString(),
            default => $this->from,
        };

        $this->to = match ($period) {
            '7d', '15d', '30d', '90d', 'month' => now()->toDateString(),
            default => $this->to,
        };
    }

    protected function filteredQuery()
    {
        return $this->patient
            ->compulsionLogs()
            ->when($this->from, fn ($q) => $q->whereDate('occurred_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('occurred_at', '<=', $this->to))
            ->when($this->compulsion, fn ($q) => $q->where('compulsion_id', $this->compulsion));
    }

    #[Computed]
    public function compulsions()
    {
        return $this->patient->compulsions()->orderBy('name')->get();
    }

    /** Todos os registros do período (sem paginação), base do resumo e dos padrões. */
    #[Computed]
    public function periodLogs(): Collection
    {
        return $this->filteredQuery()->with('feelings')->get();
    }

    #[Computed]
    public function summary(): Collection
    {
        $lastGaveIn = $this->patient->compulsionLogs()
            ->where('outcome', CompulsionLog::OUTCOME_GAVE_IN)
            ->selectRaw('compulsion_id, max(occurred_at) as last_at')
            ->groupBy('compulsion_id')
            ->pluck('last_at', 'compulsion_id');

        return $this->compulsions
            ->filter(fn ($c) => ! $this->compulsion || $c->id === $this->compulsion)
            ->map(function ($compulsion) use ($lastGaveIn) {
                $logs = $this->periodLogs->where('compulsion_id', $compulsion->id);
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
    protected function topFeelings(string $moment, ?string $outcome = null, int $limit = 5): Collection
    {
        return $this->periodLogs
            ->when($outcome, fn ($logs) => $logs->where('outcome', $outcome))
            ->flatMap(fn ($log) => $log->feelings->where('pivot.moment', $moment))
            ->countBy('name')
            ->sortDesc()
            ->take($limit)
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
            ->values();
    }

    #[Computed]
    public function patterns(): array
    {
        $logs = $this->periodLogs;

        $byTime = collect(self::TIME_BUCKETS)->map(fn ($label, $key) => [
            'label' => $label,
            'gave_in' => 0,
            'resisted' => 0,
        ])->all();

        $byWeekday = collect(self::WEEKDAYS)->map(fn ($label) => [
            'label' => $label,
            'gave_in' => 0,
            'resisted' => 0,
        ])->all();

        foreach ($logs as $log) {
            $hour = $log->occurred_at->hour;
            $bucket = match (true) {
                $hour < 6 => 'madrugada',
                $hour < 12 => 'manha',
                $hour < 18 => 'tarde',
                default => 'noite',
            };

            $byTime[$bucket][$log->outcome]++;
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

    #[Computed]
    public function logs()
    {
        return $this->filteredQuery()
            ->with(['compulsion', 'feelings'])
            ->orderByDesc('occurred_at')
            ->paginate(20);
    }

    public function confirmDelete(int $id): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(int $id): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->patient->compulsionLogs()->whereKey($id)->delete();
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        return view('livewire.compulsion.history');
    }
}
