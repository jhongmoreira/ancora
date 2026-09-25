<div class="max-w-xl">
    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div>
            <x-input-label for="full_name" value="Nome completo" />
            <x-text-input wire:model="full_name" id="full_name" class="block mt-1 w-full" type="text" required />
            <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="birth_date" value="Data de nascimento" />
            <x-text-input wire:model="birth_date" id="birth_date" class="block mt-1 w-full" type="date" required />
            @if ($patient->birth_date)
                <p class="mt-1 text-sm text-gray-500">Idade atual: {{ $patient->age }} anos</p>
            @endif
            <x-input-error :messages="$errors->get('birth_date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="gender" value="Gênero" />
            <select wire:model="gender" id="gender" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">Prefiro não informar agora</option>
                <option value="feminino">Feminino</option>
                <option value="masculino">Masculino</option>
                <option value="outro">Outro</option>
                <option value="prefiro_nao_informar">Prefiro não informar</option>
            </select>
            <x-input-error :messages="$errors->get('gender')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="contact" value="Contato (opcional)" />
            <x-text-input wire:model="contact" id="contact" class="block mt-1 w-full" type="text" />
            <x-input-error :messages="$errors->get('contact')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="notes" value="Observações (opcional)" />
            <textarea wire:model="notes" id="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="border-t border-gray-200 pt-6">
            <x-input-label for="professional_id" value="Psicóloga(o) vinculada(o)" />

            @if (! $addingProfessional)
                <div class="flex items-center gap-3 mt-1">
                    <select wire:model="professional_id" id="professional_id" class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">Nenhuma selecionada</option>
                        @foreach ($professionals as $professional)
                            <option value="{{ $professional->id }}">{{ $professional->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="toggleAddProfessional" class="whitespace-nowrap text-sm text-indigo-600 hover:text-indigo-800">
                        + Nova
                    </button>
                </div>
                <x-input-error :messages="$errors->get('professional_id')" class="mt-2" />
            @else
                <div class="mt-1 space-y-3 rounded-md border border-gray-200 p-4">
                    <div>
                        <x-input-label for="new_professional_name" value="Nome da psicóloga(o)" />
                        <x-text-input wire:model="new_professional_name" id="new_professional_name" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('new_professional_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="new_professional_contact" value="Contato (opcional)" />
                        <x-text-input wire:model="new_professional_contact" id="new_professional_contact" class="block mt-1 w-full" type="text" />
                    </div>
                    <button type="button" wire:click="toggleAddProfessional" class="text-sm text-gray-500 hover:text-gray-700">
                        Cancelar
                    </button>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Salvar</x-primary-button>
        </div>
    </form>
</div>
