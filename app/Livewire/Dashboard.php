<?php

namespace App\Livewire;

use App\Livewire\Concerns\GuardsSharedAccess;
use App\Models\Patient;
use App\Services\Stats\EmotionStats;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Dashboard extends Component
{
    use GuardsSharedAccess;

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
        $this->statsCache = null;
    }

    /**
     * Os cálculos vivem em EmotionStats (reaproveitados pelos insights com
     * IA, docs/15); o componente só delega. Recriado quando o período muda.
     */
    protected ?EmotionStats $statsCache = null;

    protected function stats(): EmotionStats
    {
        return $this->statsCache ??= new EmotionStats($this->patient, $this->from, $this->to);
    }

    #[Computed]
    public function moodCategories()
    {
        return $this->stats()->moodCategories();
    }

    public function chartData(): array
    {
        return $this->stats()->chartData();
    }

    /**
     * "Quantos dos dias do período você registrou pelo menos uma vez".
     */
    public function consistencyData(): array
    {
        return $this->stats()->consistency();
    }

    public function streakData(): array
    {
        return $this->stats()->streak();
    }

    public function heatmapData(): array
    {
        return $this->stats()->heatmap();
    }

    public function triggersData(): array
    {
        return $this->stats()->triggers();
    }

    public function summaryText(): string
    {
        return $this->stats()->summaryText();
    }

    public function recoveryData(): array
    {
        return $this->stats()->recovery();
    }

    public function formatRecoveryDuration(float $hours): string
    {
        return EmotionStats::formatRecoveryDuration($hours);
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
