<?php

namespace App\Livewire;

use App\Models\MoodCategory;
use App\Models\Patient;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Dashboard extends Component
{
    protected const COLORS = [
        'green' => '#16a34a',
        'gray' => '#6b7280',
        'red' => '#dc2626',
    ];

    public ?Patient $patient = null;

    public bool $readOnly = false;

    public string $period = '30d';

    public ?string $from = null;

    public ?string $to = null;

    /**
     * @param  Patient|null  $patient  Quando omitido, usa o paciente do usuário
     *                                 autenticado (uso normal). Passado explicitamente
     *                                 (com $readOnly=true) na visão compartilhada com a
     *                                 psicóloga (ver ShareLink).
     */
    public function mount(?Patient $patient = null, bool $readOnly = false): void
    {
        $this->patient = $patient ?? Auth::user()?->patient;
        $this->readOnly = $readOnly;
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

    protected function logsInPeriod()
    {
        return $this->patient
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

    protected const PERIODS_OF_DAY = [
        'Manhã' => [0, 11],
        'Tarde' => [12, 17],
        'Noite' => [18, 23],
    ];

    protected const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    /**
     * Concentração de registros de humor NEGATIVO por dia da semana x
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

    public function render()
    {
        return view('livewire.dashboard', [
            'chartData' => $this->chartData(),
            'consistency' => $this->consistencyData(),
            'heatmap' => $this->heatmapData(),
        ]);
    }
}
