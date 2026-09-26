<div class="bg-white shadow sm:rounded-lg p-6" x-data="{ open: {{ $this->compulsions->isEmpty() ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between gap-4">
        <h3 class="text-sm font-semibold text-gray-700">Suas compulsões</h3>
        <button type="button" x-on:click="open = !open" class="text-xs text-indigo-600 hover:text-indigo-800" x-text="open ? 'fechar' : 'gerenciar'"></button>
    </div>

    @if ($this->compulsions->isEmpty())
        <p class="text-sm text-gray-500 mt-2">
            Cadastre aqui os comportamentos que você quer acompanhar (ex.: pornografia, compras, comer por impulso). Só você e quem você compartilhar vão ver.
        </p>
    @else
        <div class="flex flex-wrap gap-2 mt-3" x-show="!open">
            @foreach ($this->compulsions->reject->isArchived() as $compulsion)
                <span class="text-sm px-3 py-1 rounded-full bg-indigo-50 text-indigo-700">{{ $compulsion->name }}</span>
            @endforeach
        </div>
    @endif

    <div x-show="open" x-cloak>
        @if ($this->compulsions->isNotEmpty())
            <ul class="divide-y divide-gray-100 mt-3">
                @foreach ($this->compulsions as $compulsion)
                    <li wire:key="compulsion-{{ $compulsion->id }}" class="py-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <span @class(['font-medium', 'text-gray-800' => ! $compulsion->isArchived(), 'text-gray-400' => $compulsion->isArchived()])>{{ $compulsion->name }}</span>
                            @if ($compulsion->isArchived())
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 ml-2">arquivada</span>
                            @endif
                            @if ($compulsion->description)
                                <p class="text-xs text-gray-500 truncate">{{ $compulsion->description }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <button type="button" wire:click="toggleArchive({{ $compulsion->id }})" class="text-xs text-indigo-600 hover:text-indigo-800">
                                {{ $compulsion->isArchived() ? 'Reativar' : 'Arquivar' }}
                            </button>
                            @if ($compulsion->logs_count === 0)
                                <button type="button" wire:click="delete({{ $compulsion->id }})" wire:confirm="Excluir esta compulsão?" class="text-xs text-gray-400 hover:text-red-600">
                                    Excluir
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form wire:submit="add" class="flex flex-wrap items-end gap-3 pt-4 mt-3 border-t border-gray-100">
            <div class="flex-1 min-w-[150px]">
                <x-input-label for="compulsion-name" value="Nome" />
                <x-text-input wire:model="name" id="compulsion-name" class="mt-1 block w-full text-sm" type="text" placeholder="Ex.: Pornografia" maxlength="80" />
            </div>
            <div class="flex-[2] min-w-[200px]">
                <x-input-label for="compulsion-description" value="Descrição (opcional)" />
                <x-text-input wire:model="description" id="compulsion-description" class="mt-1 block w-full text-sm" type="text" placeholder="Algo que ajude a identificar" maxlength="500" />
            </div>
            <x-primary-button>Adicionar</x-primary-button>
        </form>
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>
</div>
