<?php

namespace App\Livewire\Compulsion;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Manager extends Component
{
    public string $name = '';

    public string $description = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    #[Computed]
    public function compulsions()
    {
        return Auth::user()->patient->compulsions()
            ->withCount('logs')
            ->orderByRaw('archived_at is not null')
            ->orderBy('name')
            ->get();
    }

    public function add(): void
    {
        $validated = $this->validate();

        Auth::user()->patient->compulsions()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
        ]);

        $this->reset(['name', 'description']);
        unset($this->compulsions);
    }

    public function toggleArchive(int $compulsionId): void
    {
        $compulsion = Auth::user()->patient->compulsions()->whereKey($compulsionId)->firstOrFail();

        $compulsion->update(['archived_at' => $compulsion->isArchived() ? null : now()]);
        unset($this->compulsions);
    }

    /**
     * Só permite excluir compulsões sem registros — com histórico, o caminho
     * é arquivar, para não apagar dados clínicos por engano.
     */
    public function delete(int $compulsionId): void
    {
        Auth::user()->patient->compulsions()->whereKey($compulsionId)->doesntHave('logs')->delete();
        unset($this->compulsions);
    }

    public function render()
    {
        return view('livewire.compulsion.manager');
    }
}
