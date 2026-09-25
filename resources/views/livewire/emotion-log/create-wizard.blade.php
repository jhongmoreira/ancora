<div class="max-w-lg mx-auto">
    @if ($justSaved)
        <div class="text-center py-10">
            <div class="text-5xl mb-4">✅</div>
            <h3 class="text-lg font-semibold text-gray-800">Registro salvo!</h3>
            <p class="text-gray-500 mt-1">Seu registro emocional foi guardado com sucesso.</p>

            <div class="mt-6 flex justify-center gap-3">
                <button type="button" wire:click="startAnother" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                    Fazer outro registro
                </button>
                <a href="{{ route('emotion-logs.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Ver histórico
                </a>
            </div>
        </div>
    @else
        <!-- Indicador de progresso -->
        <div class="flex items-center justify-center gap-2 mb-6">
            @for ($i = 1; $i <= 5; $i++)
                <div class="h-1.5 w-8 rounded-full {{ $i <= $step ? 'bg-indigo-600' : 'bg-gray-200' }}"></div>
            @endfor
        </div>

        {{-- Passo 1: Humor + intensidade --}}
        @if ($step === 1)
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Como você está?</h3>

                <label class="text-xs text-gray-500 mb-1 block">Registrando para</label>
                <input type="datetime-local" wire:model="occurred_at" class="mb-4 block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <x-input-error :messages="$errors->get('occurred_at')" class="mb-4" />

                <div class="grid grid-cols-1 gap-3">
                    @foreach ($this->moodCategories as $category)
                        <button
                            type="button"
                            wire:click="selectMood({{ $category->id }})"
                            class="flex items-center justify-between px-4 py-4 rounded-lg border-2 text-left transition
                                {{ $mood_category_id === $category->id ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}"
                        >
                            <span class="font-medium text-gray-800">{{ $category->label }}</span>
                            @if ($mood_category_id === $category->id)
                                <span class="text-indigo-600">✓</span>
                            @endif
                        </button>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('mood_category_id')" class="mt-2" />

                <div class="mt-6">
                    <p class="text-sm font-medium text-gray-700 mb-2">Intensidade (opcional)</p>
                    <div class="flex gap-2">
                        @for ($i = 1; $i <= 5; $i++)
                            <button
                                type="button"
                                wire:click="$set('intensity', {{ $intensity === $i ? 'null' : $i }})"
                                class="h-10 w-10 rounded-full border-2 text-sm font-semibold
                                    {{ $intensity === $i ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}"
                            >
                                {{ $i }}
                            </button>
                        @endfor
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="button" wire:click="next" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Próximo
                    </button>
                </div>
            </div>
        @endif

        {{-- Passo 2: Sentimentos --}}
        @if ($step === 2)
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4">O que você sentiu?</h3>

                <div class="flex flex-wrap gap-2">
                    @foreach ($this->availableFeelings as $feeling)
                        <button
                            type="button"
                            wire:click="toggleFeeling({{ $feeling->id }})"
                            class="px-3 py-2 rounded-full border-2 text-sm
                                {{ in_array($feeling->id, $feeling_ids) ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}"
                        >
                            {{ $feeling->name }}
                        </button>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('feeling_ids')" class="mt-2" />

                <div class="mt-8 flex justify-between">
                    <button type="button" wire:click="back" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        Voltar
                    </button>
                    <button type="button" wire:click="next" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Próximo
                    </button>
                </div>
            </div>
        @endif

        {{-- Passo 3: Situação --}}
        @if ($step === 3)
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4">O que aconteceu?</h3>

                <textarea wire:model="situation" rows="5" autofocus placeholder="Descreva a situação..." class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <x-input-error :messages="$errors->get('situation')" class="mt-2" />

                <div class="mt-8 flex justify-between">
                    <button type="button" wire:click="back" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        Voltar
                    </button>
                    <button type="button" wire:click="next" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Próximo
                    </button>
                </div>
            </div>
        @endif

        {{-- Passo 4: Ação --}}
        @if ($step === 4)
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4">O que você fez?</h3>

                <textarea wire:model="action" rows="5" autofocus placeholder="Descreva a ação..." class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <x-input-error :messages="$errors->get('action')" class="mt-2" />

                <div class="mt-8 flex flex-wrap justify-between gap-2">
                    <button type="button" wire:click="back" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        Voltar
                    </button>
                    <div class="flex gap-2">
                        <button type="button" wire:click="save" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                            Salvar
                        </button>
                        <button type="button" wire:click="next" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Adicionar pensamento
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Passo 5: Pensamento automático (opcional) --}}
        @if ($step === 5)
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Qual pensamento passou pela sua cabeça?</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Pensamento automático é aquele pensamento rápido que passou pela sua cabeça diante da situação — é opcional, mas costuma ajudar bastante na terapia.
                </p>

                <textarea wire:model="automatic_thought" rows="4" autofocus placeholder="(opcional)" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <x-input-error :messages="$errors->get('automatic_thought')" class="mt-2" />

                <div class="mt-8 flex flex-wrap justify-between gap-2">
                    <button type="button" wire:click="back" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        Voltar
                    </button>
                    <div class="flex gap-2">
                        <button type="button" wire:click="skipThought" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                            Pular e salvar
                        </button>
                        <button type="button" wire:click="save" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Salvar
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
