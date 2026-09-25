<?php

namespace App\Livewire\EmotionLog;

use App\Models\Feeling;
use App\Models\MoodCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class History extends Component
{
    use WithPagination;

    #[Url]
    public string $period = '30d';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    /**
     * Sem tipo declarado de propósito: uma URL antiga/malformada (ex.:
     * `?mood=false`) não pode travar a hidratação do componente com um
     * TypeError. Normalizamos para array em mount().
     *
     * @var array<int>
     */
    #[Url]
    public $mood = [];

    /** @var array<int> */
    #[Url]
    public $feelings = [];

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->mood = is_array($this->mood) ? array_map('intval', $this->mood) : [];
        $this->feelings = is_array($this->feelings) ? array_map('intval', $this->feelings) : [];
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

    public function updatedMood(): void
    {
        $this->resetPage();
    }

    public function updatedFeelings(): void
    {
        $this->resetPage();
    }

    public function toggleMood(int $moodCategoryId): void
    {
        $current = is_array($this->mood) ? $this->mood : [];

        $this->mood = in_array($moodCategoryId, $current, true)
            ? array_values(array_diff($current, [$moodCategoryId]))
            : [...$current, $moodCategoryId];

        $this->resetPage();
    }

    protected function applyPeriodPreset(string $period): void
    {
        $this->from = match ($period) {
            '7d' => now()->subDays(6)->toDateString(),
            '30d' => now()->subDays(29)->toDateString(),
            'month' => now()->startOfMonth()->toDateString(),
            default => $this->from,
        };

        $this->to = match ($period) {
            '7d', '30d', 'month' => now()->toDateString(),
            default => $this->to,
        };
    }

    #[Computed]
    public function moodCategories()
    {
        return MoodCategory::orderBy('order')->get();
    }

    #[Computed]
    public function feelingOptions()
    {
        return Feeling::orderBy('order')->get()->groupBy('mood_category_id');
    }

    #[Computed]
    public function logs()
    {
        return Auth::user()->patient
            ->emotionLogs()
            ->with(['moodCategory', 'feelings'])
            ->when($this->from, fn ($q) => $q->whereDate('occurred_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('occurred_at', '<=', $this->to))
            ->when($this->mood, fn ($q) => $q->whereIn('mood_category_id', $this->mood))
            ->when($this->feelings, fn ($q) => $q->whereHas('feelings', fn ($f) => $f->whereIn('feelings.id', $this->feelings)))
            ->orderByDesc('occurred_at')
            ->paginate(20);
    }

    #[Computed]
    public function groupedLogs()
    {
        return $this->logs->getCollection()->groupBy(fn ($log) => $log->occurred_at->toDateString());
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(int $id): void
    {
        Auth::user()->patient->emotionLogs()->whereKey($id)->delete();
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        return view('livewire.emotion-log.history');
    }
}
