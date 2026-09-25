<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ReportsIndex extends Component
{
    public string $period = '30d';

    public string $from;

    public string $to;

    public function mount(): void
    {
        $this->applyPeriodPreset($this->period);
    }

    public function updatedPeriod(string $value): void
    {
        $this->applyPeriodPreset($value);
    }

    public function updatedFrom(): void
    {
        $this->period = 'custom';
    }

    public function updatedTo(): void
    {
        $this->period = 'custom';
    }

    protected function applyPeriodPreset(string $period): void
    {
        $this->from = match ($period) {
            '7d' => now()->subDays(6)->toDateString(),
            'month' => now()->startOfMonth()->toDateString(),
            'all' => optional(Auth::user()->patient->emotionLogs()->oldest('occurred_at')->first())->occurred_at?->toDateString()
                ?? now()->toDateString(),
            'custom' => $this->from ?? now()->subDays(29)->toDateString(),
            default => now()->subDays(29)->toDateString(),
        };

        $this->to = $period === 'custom' ? ($this->to ?? now()->toDateString()) : now()->toDateString();
    }

    #[Computed]
    public function count(): int
    {
        return Auth::user()->patient
            ->emotionLogs()
            ->whereDate('occurred_at', '>=', $this->from)
            ->whereDate('occurred_at', '<=', $this->to)
            ->count();
    }

    public function render()
    {
        return view('livewire.reports-index');
    }
}
