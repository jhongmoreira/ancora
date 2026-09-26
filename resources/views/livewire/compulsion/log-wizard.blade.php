@php
    $gaveIn = $outcome === \App\Models\CompulsionLog::OUTCOME_GAVE_IN;
    $primaryButton = 'inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700';
    $secondaryButton = 'inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50';
    $textarea = 'block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
@endphp

<div class="max-w-lg mx-auto">
    @if ($justSaved)
        <div class="text-center py-10">
            @if ($savedOutcome === \App\Models\CompulsionLog::OUTCOME_RESISTED)
                <div class="text-5xl mb-4">💪</div>
                <h3 class="text-lg font-semibold text-gray-800">Você resistiu!</h3>
                <p class="text-gray-500 mt-1">Reconhecer a vontade e passar por ela é exatamente o que se treina na terapia.</p>
            @else
                <div class="text-5xl mb-4">🤝</div>
                <h3 class="text-lg font-semibold text-gray-800">Registro salvo</h3>
                <p class="text-gray-500 mt-1">Registrar já é um passo. Um deslize não apaga seu progresso, e esse registro vai ajudar na sua terapia.</p>
            @endif

            <div class="mt-6 flex justify-center gap-3">
                <button type="button" wire:click="startAnother" class="{{ $primaryButton }}">Novo registro</button>
                <a href="{{ route('compulsions.index') }}" wire:navigate class="{{ $secondaryButton }}">Ver mapa</a>
            </div>
        </div>
    @elseif ($this->compulsions->isEmpty())
        <div class="text-center py-10">
            <p class="text-gray-600">Você ainda não cadastrou nenhuma compulsão.</p>
            <a href="{{ route('compulsions.index') }}" wire:navigate class="mt-4 {{ $primaryButton }}">Cadastrar compulsão</a>
        </div>
    @else
        <!-- Indicador de progresso -->
        <div class="flex items-center justify-center gap-2 mb-6">
            @for ($i = 1; $i <= \App\Livewire\Compulsion\LogWizard::LAST_STEP; $i++)
                <div class="h-1.5 w-8 rounded-full {{ $i <= $step ? 'bg-indigo-600' : 'bg-gray-200' }}"></div>
            @endfor
        </div>

        {{-- Passo 1: compulsão, quando, desfecho e intensidade --}}
        @if ($step === 1)
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4">O que aconteceu?</h3>

                @if ($this->compulsions->count() > 1)
                    <p class="text-sm font-medium text-gray-700 mb-2">Qual compulsão?</p>
                    <div class="flex flex-wrap gap-2 mb-2">
                        @foreach ($this->compulsions as $compulsion)
                            <button
                                type="button"
                                wire:click="$set('compulsion_id', {{ $compulsion->id }})"
                                class="px-3 py-2 rounded-full border-2 text-sm
                                    {{ $compulsion_id === $compulsion->id ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}"
                            >
                                {{ $compulsion->name }}
                            </button>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 mb-2">Compulsão: <span class="font-medium text-gray-800">{{ $this->compulsions->first()->name }}</span></p>
                @endif
                <x-input-error :messages="$errors->get('compulsion_id')" class="mb-2" />

                <label class="text-xs text-gray-500 mb-1 mt-4 block">Quando foi?</label>
                <input type="datetime-local" wire:model="occurred_at" max="{{ now()->format('Y-m-d\TH:i') }}" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <x-input-error :messages="$errors->get('occurred_at')" class="mt-2" />

                <p class="text-sm font-medium text-gray-700 mb-2 mt-6">Você...</p>
                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        wire:click="$set('outcome', '{{ \App\Models\CompulsionLog::OUTCOME_RESISTED }}')"
                        class="px-4 py-4 rounded-lg border-2 text-center transition
                            {{ $outcome === \App\Models\CompulsionLog::OUTCOME_RESISTED ? 'border-green-600 bg-green-50' : 'border-gray-200 hover:border-gray-300' }}"
                    >
                        <span class="block font-medium text-gray-800">Resisti</span>
                        <span class="block text-xs text-gray-500">senti a vontade, mas não fiz</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('outcome', '{{ \App\Models\CompulsionLog::OUTCOME_GAVE_IN }}')"
                        class="px-4 py-4 rounded-lg border-2 text-center transition
                            {{ $gaveIn ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}"
                    >
                        <span class="block font-medium text-gray-800">Cedi</span>
                        <span class="block text-xs text-gray-500">acabei fazendo</span>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('outcome')" class="mt-2" />

                <p class="text-sm font-medium text-gray-700 mb-1 mt-6">Qual era a força da vontade?</p>
                <p class="text-xs text-gray-500 mb-2">0 = nenhuma · 10 = a mais forte que já sentiu</p>
                <div class="flex flex-wrap gap-1.5">
                    @for ($i = 0; $i <= 10; $i++)
                        <button
                            type="button"
                            wire:click="$set('urge_intensity', {{ $i }})"
                            class="h-9 w-9 rounded-full border-2 text-sm font-semibold
                                {{ $urge_intensity === $i ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}"
                        >
                            {{ $i }}
                        </button>
                    @endfor
                </div>
                <x-input-error :messages="$errors->get('urge_intensity')" class="mt-2" />

                <div class="mt-8 flex justify-end">
                    <button type="button" wire:click="next" class="{{ $primaryButton }}">Próximo</button>
                </div>
            </div>
        @endif

        {{-- Passo 2: antes — gatilho, sentimentos e pensamento --}}
        @if ($step === 2)
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-1">Antes: o que estava acontecendo?</h3>
                <p class="text-sm text-gray-500 mb-4">Onde você estava, com quem, o que estava fazendo ou o que tinha acabado de acontecer.</p>

                <textarea wire:model="trigger" rows="4" placeholder="Ex.: Sozinho no quarto, à noite, depois de uma discussão no trabalho..." class="{{ $textarea }}"></textarea>
                <x-input-error :messages="$errors->get('trigger')" class="mt-2" />

                <p class="text-sm font-medium text-gray-700 mb-2 mt-6">O que você sentia antes?</p>
                @include('livewire.compulsion.partials.feeling-picker', ['moment' => \App\Models\CompulsionLog::MOMENT_BEFORE, 'selected' => $feelings_before])
                <x-input-error :messages="$errors->get('feelings_before')" class="mt-2" />

                <p class="text-sm font-medium text-gray-700 mb-1 mt-6">Que pensamento passou pela sua cabeça? <span class="font-normal text-gray-400">(opcional)</span></p>
                <p class="text-xs text-gray-500 mb-2">Ex.: "só dessa vez", "eu mereço", "não aguento mais esse dia".</p>
                <textarea wire:model="automatic_thought" rows="3" class="{{ $textarea }}"></textarea>
                <x-input-error :messages="$errors->get('automatic_thought')" class="mt-2" />

                <div class="mt-8 flex justify-between">
                    <button type="button" wire:click="back" class="{{ $secondaryButton }}">Voltar</button>
                    <button type="button" wire:click="next" class="{{ $primaryButton }}">Próximo</button>
                </div>
            </div>
        @endif

        {{-- Passo 3: depois — sentimentos, duração e estratégia --}}
        @if ($step === 3)
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Depois: como você ficou?</h3>

                @include('livewire.compulsion.partials.feeling-picker', ['moment' => \App\Models\CompulsionLog::MOMENT_AFTER, 'selected' => $feelings_after])
                <x-input-error :messages="$errors->get('feelings_after')" class="mt-2" />

                @if ($gaveIn)
                    <label for="duration" class="text-sm font-medium text-gray-700 mb-2 mt-6 block">Quanto tempo durou? <span class="font-normal text-gray-400">(minutos, opcional)</span></label>
                    <input type="number" id="duration" wire:model="duration_minutes" min="1" max="1440" inputmode="numeric" class="block w-32 text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                @endif

                <p class="text-sm font-medium text-gray-700 mb-2 mt-6">
                    {{ $gaveIn ? 'O que você poderia ter feito de diferente?' : 'O que te ajudou a resistir?' }}
                    <span class="font-normal text-gray-400">(opcional)</span>
                </p>
                <textarea
                    wire:model="coping_strategy"
                    rows="3"
                    placeholder="{{ $gaveIn ? 'Ex.: sair do quarto, ligar para alguém, deixar o celular na sala...' : 'Ex.: fui caminhar, tomei banho, conversei com alguém...' }}"
                    class="{{ $textarea }}"
                ></textarea>
                <x-input-error :messages="$errors->get('coping_strategy')" class="mt-2" />

                <p class="text-sm font-medium text-gray-700 mb-2 mt-6">Observações <span class="font-normal text-gray-400">(opcional)</span></p>
                <textarea wire:model="notes" rows="2" class="{{ $textarea }}"></textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />

                <div class="mt-8 flex justify-between">
                    <button type="button" wire:click="back" class="{{ $secondaryButton }}">Voltar</button>
                    <button type="button" wire:click="save" class="{{ $primaryButton }}">Salvar</button>
                </div>
            </div>
        @endif
    @endif
</div>
