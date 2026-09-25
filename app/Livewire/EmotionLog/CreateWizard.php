<?php

namespace App\Livewire\EmotionLog;

use App\Models\MoodCategory;
use App\Services\EmotionLogService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CreateWizard extends Component
{
    public int $step = 1;

    public ?int $mood_category_id = null;

    public ?int $intensity = null;

    /** @var array<int> */
    public array $feeling_ids = [];

    public string $situation = '';

    public string $action = '';

    public ?string $automatic_thought = null;

    public string $occurred_at = '';

    public bool $justSaved = false;

    public function mount(): void
    {
        $this->occurred_at = now()->format('Y-m-d\TH:i');
    }

    #[Computed]
    public function moodCategories()
    {
        return MoodCategory::with('feelings')->orderBy('order')->get();
    }

    #[Computed]
    public function availableFeelings()
    {
        $category = $this->moodCategories->firstWhere('id', $this->mood_category_id);

        return $category?->feelings->sortBy('order') ?? collect();
    }

    public function selectMood(int $moodCategoryId): void
    {
        if ($this->mood_category_id !== $moodCategoryId) {
            $this->feeling_ids = [];
        }

        $this->mood_category_id = $moodCategoryId;
    }

    public function toggleFeeling(int $feelingId): void
    {
        if (in_array($feelingId, $this->feeling_ids, true)) {
            $this->feeling_ids = array_values(array_diff($this->feeling_ids, [$feelingId]));
        } else {
            $this->feeling_ids[] = $feelingId;
        }
    }

    protected function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'mood_category_id' => ['required', 'exists:mood_categories,id'],
                'intensity' => ['nullable', 'integer', 'between:1,5'],
                'occurred_at' => ['required', 'date'],
            ],
            2 => [
                'feeling_ids' => ['required', 'array', 'min:1'],
                'feeling_ids.*' => ['integer', 'exists:feelings,id'],
            ],
            3 => ['situation' => ['required', 'string']],
            4 => ['action' => ['required', 'string']],
            5 => ['automatic_thought' => ['nullable', 'string']],
            default => [],
        };
    }

    public function next(): void
    {
        $this->validate($this->rulesForStep($this->step));

        $this->step = min($this->step + 1, 5);
    }

    public function back(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    public function skipThought(): void
    {
        $this->automatic_thought = null;
        $this->save();
    }

    public function save(): void
    {
        $rules = array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3),
            $this->rulesForStep(4),
            $this->rulesForStep(5),
        );

        $validated = $this->validate($rules);

        app(EmotionLogService::class)->create(Auth::user()->patient, $validated);

        $this->reset(['mood_category_id', 'intensity', 'feeling_ids', 'situation', 'action', 'automatic_thought']);
        $this->step = 1;
        $this->occurred_at = now()->format('Y-m-d\TH:i');
        $this->justSaved = true;
    }

    public function startAnother(): void
    {
        $this->justSaved = false;
    }

    public function render()
    {
        return view('livewire.emotion-log.create-wizard');
    }
}
