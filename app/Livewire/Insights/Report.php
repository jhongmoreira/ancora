<?php

namespace App\Livewire\Insights;

use App\Livewire\Concerns\GuardsSharedAccess;
use App\Models\CompulsionLog;
use App\Models\EmotionLog;
use App\Models\InsightReport;
use App\Models\Patient;
use App\Services\Insights\InsightDataBuilder;
use App\Services\Insights\InsightReportService;
use App\Services\Insights\InsightUnavailableException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tela de insights com IA (docs/15). O paciente gera; na visão compartilhada
 * ($readOnly=true) a psicóloga só lê — a geração é bloqueada no servidor.
 */
class Report extends Component
{
    use GuardsSharedAccess;

    public ?Patient $patient = null;

    #[Locked]
    public bool $readOnly = false;

    /** Relatório aberto; nulo = o mais recente. Sem tipo: URL malformada não pode quebrar a hidratação. */
    #[Url(as: 'relatorio')]
    public $selected = null;

    public ?string $message = null;

    /** Período do próximo relatório: opção do seletor e datas (editáveis no personalizado). */
    public string $period = InsightDataBuilder::DEFAULT_PERIOD;

    public ?string $from = null;

    public ?string $to = null;

    public function mount(?Patient $patient = null, bool $readOnly = false, ?string $shareToken = null): void
    {
        $this->patient = $patient ?? Auth::user()?->patient;
        $this->readOnly = $readOnly;
        $this->shareToken = $shareToken;
        $this->selected = is_numeric($this->selected) ? (int) $this->selected : null;
        $this->applyPeriodPreset($this->period);
    }

    public function updatedPeriod(string $value): void
    {
        if (! array_key_exists($value, InsightDataBuilder::PERIODS)) {
            $this->period = InsightDataBuilder::DEFAULT_PERIOD;
        }

        $this->applyPeriodPreset($this->period);
    }

    public function updatedFrom(): void
    {
        $this->period = 'custom';
    }

    public function updatedTo(): void
    {
        $this->period = 'custom';
    }

    /** No personalizado, mantém as datas já preenchidas como ponto de partida. */
    protected function applyPeriodPreset(string $period): void
    {
        if ($period === 'custom' && $this->from && $this->to) {
            return;
        }

        ['period_start' => $this->from, 'period_end' => $this->to] = InsightDataBuilder::resolvePeriod(
            $period === 'custom' ? InsightDataBuilder::DEFAULT_PERIOD : $period
        );
    }

    /**
     * A psicóloga só vê relatórios concluídos; o paciente também vê a
     * última tentativa com falha, para entender o que houve.
     */
    #[Computed]
    public function reports(): Collection
    {
        return $this->patient->insightReports()
            ->when($this->readOnly, fn ($q) => $q->where('status', InsightReport::STATUS_COMPLETED))
            ->latest('id')
            ->get(['id', 'patient_id', 'period_preset', 'period_start', 'period_end', 'status', 'created_at']);
    }

    #[Computed]
    public function report(): ?InsightReport
    {
        $completed = $this->reports->where('status', InsightReport::STATUS_COMPLETED);
        $id = $this->selected && $this->reports->contains('id', $this->selected)
            ? $this->selected
            : $completed->first()?->id;

        return $id ? $this->patient->insightReports()->find($id) : null;
    }

    /** Última tentativa, se falhou e é mais nova que o último relatório concluído. */
    #[Computed]
    public function lastFailure(): ?InsightReport
    {
        $latest = $this->reports->first();

        return ! $this->readOnly && $latest?->status === InsightReport::STATUS_FAILED
            ? $this->patient->insightReports()->find($latest->id)
            : null;
    }

    /**
     * Registros citados como evidência no relatório aberto, com o texto
     * original, para o paciente/psicóloga conferirem de onde veio cada ponto.
     *
     * @return array<string, array>
     */
    #[Computed]
    public function evidence(): array
    {
        $content = $this->report?->content;

        if (! $content) {
            return [];
        }

        $ids = collect($content['padroes'])->flatMap(fn ($p) => $p['evidencias'])
            ->merge(collect($content['pensamentos'])->pluck('registro'))
            ->merge($this->report->risk_record_ids ?? [])
            ->unique();

        $emotionIds = $ids->filter(fn ($id) => str_starts_with($id, 'E'))->map(fn ($id) => (int) substr($id, 1));
        $compulsionIds = $ids->filter(fn ($id) => str_starts_with($id, 'C'))->map(fn ($id) => (int) substr($id, 1));

        $emotion = $this->patient->emotionLogs()->with('moodCategory')->whereIn('id', $emotionIds)->get()
            ->mapWithKeys(fn (EmotionLog $log) => ["E{$log->id}" => [
                'when' => $log->occurred_at->translatedFormat('D, d/m \à\s H:i'),
                'label' => 'Registro emocional · '.$log->moodCategory->label,
                'lines' => array_filter(['Situação' => $log->situation, 'Ação' => $log->action, 'Pensamento' => $log->automatic_thought]),
            ]]);

        $compulsion = $this->patient->compulsionLogs()->with('compulsion')->whereIn('id', $compulsionIds)->get()
            ->mapWithKeys(fn (CompulsionLog $log) => ["C{$log->id}" => [
                'when' => $log->occurred_at->translatedFormat('D, d/m \à\s H:i'),
                'label' => $log->compulsion->name.' · '.($log->gaveIn() ? 'Cedeu' : 'Resistiu'),
                'lines' => array_filter(['Situação' => $log->trigger, 'Pensamento' => $log->automatic_thought, 'Estratégia' => $log->coping_strategy]),
            ]]);

        return $emotion->merge($compulsion)->all();
    }

    public function select(int $id): void
    {
        $this->selected = $id;
        unset($this->report, $this->evidence);
    }

    public function acceptConsent(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->patient->update(['ai_consent_at' => now()]);
        $this->generate();
    }

    public function generate(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->message = null;

        try {
            $report = app(InsightReportService::class)->generate($this->patient, $this->period, $this->from, $this->to);
        } catch (InsightUnavailableException $e) {
            $this->message = $e->getMessage();

            return;
        }

        $this->selected = $report->isCompleted() ? $report->id : null;
        unset($this->reports, $this->report, $this->lastFailure, $this->evidence);
    }

    public function render()
    {
        return view('livewire.insights.report');
    }
}
