<div class="space-y-6">
    <div class="bg-white shadow sm:rounded-lg p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Seus lembretes ({{ $this->activeCount }}/{{ \App\Livewire\ReminderManager::MAX_ACTIVE_REMINDERS }} ativos)</h3>

        @if ($this->reminders->isEmpty())
            <p class="text-sm text-gray-400 mb-4">Nenhum lembrete configurado ainda.</p>
        @else
            <ul class="divide-y divide-gray-100 mb-4">
                @foreach ($this->reminders as $reminder)
                    <li class="py-3 flex items-center justify-between">
                        <div>
                            <span class="font-medium text-gray-800">{{ \Illuminate\Support\Carbon::parse($reminder->time)->format('H:i') }}</span>
                            @if ($reminder->label)
                                <span class="text-sm text-gray-500 ml-2">{{ $reminder->label }}</span>
                            @endif
                            @unless ($reminder->is_active)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 ml-2">inativo</span>
                            @endunless
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="toggle({{ $reminder->id }})" class="text-xs text-indigo-600 hover:text-indigo-800">
                                {{ $reminder->is_active ? 'Desativar' : 'Ativar' }}
                            </button>
                            <button type="button" wire:click="delete({{ $reminder->id }})" class="text-xs text-gray-400 hover:text-red-600">
                                Excluir
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form wire:submit="add" class="flex flex-wrap items-end gap-3 pt-4 border-t border-gray-100">
            <div>
                <x-input-label for="time" value="Horário" />
                <input type="time" wire:model="time" id="time" class="mt-1 block text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <div class="flex-1 min-w-[150px]">
                <x-input-label for="label" value="Rótulo (opcional)" />
                <x-text-input wire:model="label" id="label" class="mt-1 block w-full text-sm" type="text" placeholder="Ex.: Lembrete da manhã" />
            </div>
            <x-primary-button>Adicionar</x-primary-button>
        </form>
        <x-input-error :messages="$errors->get('time')" class="mt-2" />
    </div>
</div>
