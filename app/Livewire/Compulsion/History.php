<?php

namespace App\Livewire\Compulsion;

use App\Livewire\Concerns\GuardsSharedAccess;
use App\Models\Patient;
use App\Services\Stats\CompulsionStats;
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

    public const TIME_BUCKETS = CompulsionStats::TIME_BUCKETS;

    public const WEEKDAYS = CompulsionStats::WEEKDAYS;

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

    /** Ordem cronológica da lista: 'desc' (mais recentes primeiro, padrão) ou 'asc' (mais antigos primeiro). */
    #[Url]
    public string $order = 'desc';

    public ?int $confirmingDeleteId = null;

    public function mount(?Patient $patient = null, bool $readOnly = false, ?string $shareToken = null): void
    {
        $this->patient = $patient ?? Auth::user()?->patient;
        $this->readOnly = $readOnly;
        $this->shareToken = $shareToken;
        $this->compulsion = is_numeric($this->compulsion) ? (int) $this->compulsion : null;
        $this->order = $this->order === 'asc' ? 'asc' : 'desc';
        $this->applyPeriodPreset($this->period);
    }

    public function toggleOrder(): void
    {
        $this->order = $this->order === 'desc' ? 'asc' : 'desc';
        $this->resetPage();
    }

    public function updatedPeriod(string $value): void
    {
        $this->statsCache = null;
        $this->applyPeriodPreset($value);
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->statsCache = null;
        $this->period = 'custom';
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->statsCache = null;
        $this->period = 'custom';
        $this->resetPage();
    }

    public function updatedCompulsion($value): void
    {
        $this->statsCache = null;
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

    /**
     * Os cálculos vivem em CompulsionStats (reaproveitados pelos insights com
     * IA, docs/15). Não é propriedade pública: é recriado a cada requisição,
     * já com o período e o filtro de compulsão atuais.
     */
    protected ?CompulsionStats $statsCache = null;

    protected function stats(): CompulsionStats
    {
        return $this->statsCache ??= new CompulsionStats($this->patient, $this->from, $this->to, $this->compulsion);
    }

    #[Computed]
    public function compulsions()
    {
        return $this->stats()->compulsions();
    }

    /** Todos os registros do período (sem paginação), base do resumo e dos padrões. */
    #[Computed]
    public function periodLogs(): Collection
    {
        return $this->stats()->logs();
    }

    #[Computed]
    public function summary(): Collection
    {
        return $this->stats()->summary();
    }

    #[Computed]
    public function patterns(): array
    {
        return $this->stats()->patterns();
    }

    #[Computed]
    public function logs()
    {
        return $this->stats()->query()
            ->with(['compulsion', 'feelings'])
            ->orderBy('occurred_at', $this->order === 'asc' ? 'asc' : 'desc')
            ->paginate(20);
    }

    #[Computed]
    public function groupedLogs(): Collection
    {
        return $this->logs->getCollection()->groupBy(fn ($log) => $log->occurred_at->toDateString());
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
