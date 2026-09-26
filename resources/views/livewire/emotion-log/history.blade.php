<div>
    <!-- Filtros -->
    <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6 mb-6">
        <div class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Período</label>
                <select wire:model.live="period" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="7d">Últimos 7 dias</option>
                    <option value="15d">Última quinzena</option>
                    <option value="30d">Últimos 30 dias</option>
                    <option value="month">Este mês</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">De</label>
                <input type="date" wire:model.live="from" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Até</label>
                <input type="date" wire:model.live="to" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Humor</label>
                <div class="flex gap-1">
                    @foreach ($this->moodCategories as $category)
                        <button
                            type="button"
                            wire:click="toggleMood({{ $category->id }})"
                            @class([
                                'px-2 py-1 text-xs rounded-full border',
                                'border-indigo-600 bg-indigo-50 text-indigo-700' => in_array($category->id, $mood),
                                'border-gray-200 text-gray-600' => ! in_array($category->id, $mood),
                            ])
                        >
                            {{ $category->label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <button
                type="button"
                wire:click="toggleOrder"
                class="inline-flex items-center justify-center w-8 h-8 text-gray-600 border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                title="{{ $order === 'asc' ? 'Mais antigos primeiro (clique para inverter)' : 'Mais recentes primeiro (clique para inverter)' }}"
                aria-label="Inverter a ordem cronológica"
            >
                <svg class="w-4 h-4 transition-transform {{ $order === 'desc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h13M3 8h9M3 12h5m6 4 4 4m0 0 4-4m-4 4V8" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Lista agrupada por dia -->
    @forelse ($this->groupedLogs as $date => $dayLogs)
        <div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-500 mb-2">
                {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d \d\e F') }}
            </h4>

            <div class="space-y-3">
                @foreach ($dayLogs as $log)
                    @php
                        // Classes completas (não interpoladas) para o Tailwind detectá-las no build.
                        $moodStyle = [
                            'green' => ['card' => 'bg-green-50 border-green-500', 'tag' => 'bg-green-700 text-white'],
                            'gray' => ['card' => 'bg-blue-50 border-blue-500', 'tag' => 'bg-blue-700 text-white'],
                            'red' => ['card' => 'bg-red-50 border-red-500', 'tag' => 'bg-red-700 text-white'],
                        ][$log->moodCategory->color] ?? ['card' => 'bg-white border-gray-400', 'tag' => 'bg-gray-600 text-white'];
                    @endphp
                    <div
                        wire:key="log-{{ $log->id }}"
                        class="{{ $moodStyle['card'] }} shadow-sm sm:rounded-lg p-4 border-l-4"
                        x-data="{ expanded: false }"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-medium text-gray-800">{{ $log->occurred_at->format('H:i') }}</span>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $moodStyle['tag'] }}">{{ $log->moodCategory->label }}</span>
                                    @if ($log->intensity)
                                        <span class="text-xs text-gray-500">intensidade {{ $log->intensity }}/5</span>
                                    @endif
                                    @foreach ($log->feelings as $feeling)
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-white/80 text-gray-700 ring-1 ring-black/5">{{ $feeling->name }}</span>
                                    @endforeach
                                </div>

                                <p class="text-sm text-gray-600 mt-2" x-show="!expanded">
                                    {{ \Illuminate\Support\Str::limit($log->situation, 120) }}
                                </p>

                                <div x-show="expanded" class="mt-2 space-y-2 text-sm text-gray-600">
                                    <p><span class="font-medium text-gray-700">Situação:</span> {{ $log->situation }}</p>
                                    <p><span class="font-medium text-gray-700">Ação:</span> {{ $log->action }}</p>
                                    @if ($log->automatic_thought)
                                        <p><span class="font-medium text-gray-700">Pensamento:</span> {{ $log->automatic_thought }}</p>
                                    @endif
                                </div>

                                <button type="button" x-on:click="expanded = !expanded" class="text-xs text-indigo-600 mt-2" x-text="expanded ? 'ver menos' : 'ver completo'"></button>
                            </div>

                            @unless ($readOnly)
                                <div class="flex-shrink-0">
                                    @if ($confirmingDeleteId === $log->id)
                                        <div class="flex gap-2">
                                            <button type="button" wire:click="delete({{ $log->id }})" class="text-xs text-red-600 font-medium">Confirmar</button>
                                            <button type="button" wire:click="cancelDelete" class="text-xs text-gray-500">Cancelar</button>
                                        </div>
                                    @else
                                        <button type="button" wire:click="confirmDelete({{ $log->id }})" class="text-xs text-gray-500 hover:text-red-600">Excluir</button>
                                    @endif
                                </div>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-center py-16 text-gray-500">
            <p>Nenhum registro encontrado para o período/filtros selecionados.</p>
            @unless ($readOnly)
                <a href="{{ route('emotion-logs.create') }}" wire:navigate class="text-indigo-600 underline text-sm mt-2 inline-block">Fazer um novo registro</a>
            @endunless
        </div>
    @endforelse

    <div class="mt-4">
        {{ $this->logs->links() }}
    </div>
</div>
