<?php

namespace App\Livewire\Compulsion;

use App\Models\CompulsionLog;
use App\Models\MoodCategory;
use App\Services\CompulsionLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Registro de um impulso de compulsão em 3 passos (docs/14):
 * 1. qual compulsão, quando, desfecho e intensidade do impulso;
 * 2. antes: gatilho, sentimentos e pensamento automático;
 * 3. depois: sentimentos, duração e estratégia.
 */
class LogWizard extends Component
{
    public const LAST_STEP = 3;

    public int $step = 1;

    public ?int $compulsion_id = null;

    public string $occurred_at = '';

    public ?string $outcome = null;

    public ?int $urge_intensity = null;

    public string $trigger = '';

    /** @var array<int> */
    public array $feelings_before = [];

    public ?string $automatic_thought = null;

    /** @var array<int> */
    public array $feelings_after = [];

    public ?int $duration_minutes = null;

    public ?string $coping_strategy = null;

    public ?string $notes = null;

    public bool $justSaved = false;

    public ?string $savedOutcome = null;

    public function mount(): void
    {
        $this->occurred_at = now()->format('Y-m-d\TH:i');

        if ($this->compulsions->count() === 1) {
            $this->compulsion_id = $this->compulsions->first()->id;
        }
    }

    #[Computed]
    public function compulsions()
    {
        return Auth::user()->patient->compulsions()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function moodCategories()
    {
        return MoodCategory::with(['feelings' => fn ($q) => $q->orderBy('order')])->orderBy('order')->get();
    }

    public function toggleFeeling(string $moment, int $feelingId): void
    {
        $property = $moment === CompulsionLog::MOMENT_BEFORE ? 'feelings_before' : 'feelings_after';

        $this->{$property} = in_array($feelingId, $this->{$property}, true)
            ? array_values(array_diff($this->{$property}, [$feelingId]))
            : [...$this->{$property}, $feelingId];
    }

    protected function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'compulsion_id' => [
                    'required',
                    Rule::exists('compulsions', 'id')
                        ->where('patient_id', Auth::user()->patient->id)
                        ->whereNull('archived_at'),
                ],
                'occurred_at' => ['required', 'date', 'before_or_equal:now'],
                'outcome' => ['required', Rule::in([CompulsionLog::OUTCOME_GAVE_IN, CompulsionLog::OUTCOME_RESISTED])],
                'urge_intensity' => ['required', 'integer', 'between:0,10'],
            ],
            2 => [
                'trigger' => ['required', 'string', 'max:2000'],
                'feelings_before' => ['required', 'array', 'min:1'],
                'feelings_before.*' => ['integer', 'exists:feelings,id'],
                'automatic_thought' => ['nullable', 'string', 'max:2000'],
            ],
            3 => [
                'feelings_after' => ['required', 'array', 'min:1'],
                'feelings_after.*' => ['integer', 'exists:feelings,id'],
                'duration_minutes' => ['nullable', 'integer', 'between:1,1440'],
                'coping_strategy' => ['nullable', 'string', 'max:2000'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            default => [],
        };
    }

    protected function messages(): array
    {
        return [
            'compulsion_id.required' => 'Escolha qual compulsão.',
            'outcome.required' => 'Conte se você cedeu ou resistiu.',
            'urge_intensity.required' => 'Indique a intensidade da vontade.',
            'occurred_at.before_or_equal' => 'A data não pode estar no futuro.',
            'trigger.required' => 'Descreva o que estava acontecendo.',
            'feelings_before.required' => 'Escolha pelo menos um sentimento.',
            'feelings_after.required' => 'Escolha pelo menos um sentimento.',
        ];
    }

    public function next(): void
    {
        $this->validate($this->rulesForStep($this->step));

        $this->step = min($this->step + 1, self::LAST_STEP);
    }

    public function back(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    public function save(): void
    {
        $rules = [];
        for ($step = 1; $step <= self::LAST_STEP; $step++) {
            $rules = array_merge($rules, $this->rulesForStep($step));
        }

        $validated = $this->validate($rules);

        app(CompulsionLogService::class)->create(Auth::user()->patient, $validated);

        $this->savedOutcome = $validated['outcome'];
        $this->reset([
            'outcome', 'urge_intensity', 'trigger', 'feelings_before', 'automatic_thought',
            'feelings_after', 'duration_minutes', 'coping_strategy', 'notes',
        ]);
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
        return view('livewire.compulsion.log-wizard');
    }
}
