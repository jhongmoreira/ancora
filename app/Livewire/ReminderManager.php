<?php

namespace App\Livewire;

use App\Models\Reminder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ReminderManager extends Component
{
    public const MAX_ACTIVE_REMINDERS = 3;

    public string $time = '';

    public string $label = '';

    protected function rules(): array
    {
        return [
            'time' => ['required', 'date_format:H:i'],
            'label' => ['nullable', 'string', 'max:50'],
        ];
    }

    #[Computed]
    public function reminders()
    {
        return Auth::user()->patient->reminders()->orderBy('time')->get();
    }

    #[Computed]
    public function activeCount(): int
    {
        return $this->reminders->where('is_active', true)->count();
    }

    public function add(): void
    {
        $validated = $this->validate();

        if ($this->activeCount >= self::MAX_ACTIVE_REMINDERS) {
            $this->addError('time', 'Você já tem '.self::MAX_ACTIVE_REMINDERS.' lembretes ativos. Desative um para adicionar outro.');

            return;
        }

        Auth::user()->patient->reminders()->create([
            'time' => $validated['time'],
            'label' => $validated['label'] ?: null,
            'is_active' => true,
        ]);

        $this->reset(['time', 'label']);
        unset($this->reminders, $this->activeCount);
    }

    public function toggle(int $reminderId): void
    {
        $reminder = Auth::user()->patient->reminders()->whereKey($reminderId)->firstOrFail();

        if (! $reminder->is_active && $this->activeCount >= self::MAX_ACTIVE_REMINDERS) {
            $this->addError('time', 'Você já tem '.self::MAX_ACTIVE_REMINDERS.' lembretes ativos. Desative um para ativar outro.');

            return;
        }

        $reminder->update(['is_active' => ! $reminder->is_active]);
        unset($this->reminders, $this->activeCount);
    }

    public function delete(int $reminderId): void
    {
        Auth::user()->patient->reminders()->whereKey($reminderId)->delete();
        unset($this->reminders, $this->activeCount);
    }

    public function render()
    {
        return view('livewire.reminder-manager');
    }
}
