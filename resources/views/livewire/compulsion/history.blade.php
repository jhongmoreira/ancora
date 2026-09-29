@use('App\Models\CompulsionLog')

@php
    $patterns = $this->patterns;
@endphp

<div class="space-y-6">
    <!-- Filtros -->
    <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6">
        <div class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Período</label>
                <select wire:model.live="period" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="7d">Últimos 7 dias</option>
                    <option value="15d">Última quinzena</option>
                    <option value="30d">Últimos 30 dias</option>
                    <option value="90d">Últimos 90 dias</option>
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

            @if ($this->compulsions->count() > 1)
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Compulsão</label>
                    <select wire:model.live="compulsion" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todas</option>
                        @foreach ($this->compulsions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </div>

    @if ($this->periodLogs->isEmpty())
        <div class="bg-white shadow sm:rounded-lg text-center py-16 text-gray-500">
            <p>Nenhum registro de compulsão no período selecionado.</p>
            @unless ($readOnly)
                <a href="{{ route('compulsions.log') }}" wire:navigate class="text-indigo-600 underline text-sm mt-2 inline-block">Fazer um registro</a>
            @endunless
        </div>
    @else
        <!-- Resumo por compulsão -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->summary as $row)
                @php $total = $row['gave_in'] + $row['resisted']; @endphp
                <div wire:key="summary-{{ $row['compulsion']->id }}" class="bg-white shadow sm:rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800">{{ $row['compulsion']->name }}</h4>

                    <div class="flex gap-4 mt-3">
                        <div>
                            <p class="text-2xl font-semibold text-green-700">{{ $row['resisted'] }}</p>
                            <p class="text-xs text-gray-500">resistiu</p>
                        </div>
                        <div>
                            <p class="text-2xl font-semibold text-indigo-700">{{ $row['gave_in'] }}</p>
                            <p class="text-xs text-gray-500">cedeu</p>
                        </div>
                    </div>

                    @if ($total > 0)
                        <div class="flex h-2 rounded-full overflow-hidden bg-gray-100 mt-3" role="img" aria-label="{{ $row['resisted'] }} resistiu, {{ $row['gave_in'] }} cedeu">
                            <div class="bg-green-500" style="width: {{ $row['resisted'] / $total * 100 }}%"></div>
                            <div class="bg-indigo-500" style="width: {{ $row['gave_in'] / $total * 100 }}%"></div>
                        </div>
                    @endif

                    <dl class="mt-3 text-xs text-gray-500 space-y-0.5">
                        @if ($row['avg_urge'] !== null)
                            <div>Vontade média: <span class="font-medium text-gray-700">{{ number_format($row['avg_urge'], 1, ',', '') }}/10</span></div>
                        @endif
                        <div>
                            @if ($row['days_since_gave_in'] === null)
                                Nenhum registro de "cedeu"
                            @elseif ($row['days_since_gave_in'] === 0)
                                Último "cedeu": hoje
                            @else
                                <span class="font-medium text-gray-700">{{ $row['days_since_gave_in'] }} {{ $row['days_since_gave_in'] === 1 ? 'dia' : 'dias' }}</span> desde o último "cedeu"
                            @endif
                        </div>
                    </dl>
                </div>
            @endforeach
        </div>

        <!-- Padrões -->
        <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Padrões do período</h3>

            <div class="grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['title' => 'Sentimentos antes', 'hint' => 'possíveis gatilhos', 'items' => $patterns['before']],
                    ['title' => 'Depois de ceder', 'hint' => null, 'items' => $patterns['after_gave_in']],
                    ['title' => 'Depois de resistir', 'hint' => null, 'items' => $patterns['after_resisted']],
                ] as $group)
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-2">
                            {{ $group['title'] }}
                            @if ($group['hint'])
                                <span class="font-normal text-gray-400">· {{ $group['hint'] }}</span>
                            @endif
                        </p>
                        @forelse ($group['items'] as $item)
                            <div class="flex items-center justify-between text-sm py-0.5">
                                <span class="text-gray-700">{{ $item['name'] }}</span>
                                <span class="text-gray-500 tabular-nums">{{ $item['count'] }}×</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">—</p>
                        @endforelse
                    </div>
                @endforeach
            </div>

            <div class="grid gap-6 md:grid-cols-2 mt-8">
                @foreach ([
                    ['title' => 'Por horário', 'rows' => $patterns['by_time'], 'max' => $patterns['max_time']],
                    ['title' => 'Por dia da semana', 'rows' => $patterns['by_weekday'], 'max' => $patterns['max_weekday']],
                ] as $chart)
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-2">{{ $chart['title'] }}</p>
                        <div class="space-y-1.5">
                            @foreach ($chart['rows'] as $bar)
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="w-28 flex-shrink-0 text-gray-600">{{ $bar['label'] }}</span>
                                    <div class="flex-1 flex h-3 rounded bg-gray-50 overflow-hidden" role="img" aria-label="{{ $bar['label'] }}: {{ $bar['resisted'] }} resistiu, {{ $bar['gave_in'] }} cedeu">
                                        <div class="bg-green-500" style="width: {{ $bar['resisted'] / $chart['max'] * 100 }}%"></div>
                                        <div class="bg-indigo-500" style="width: {{ $bar['gave_in'] / $chart['max'] * 100 }}%"></div>
                                    </div>
                                    <span class="w-8 text-right text-gray-500 tabular-nums">{{ $bar['resisted'] + $bar['gave_in'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-4 mt-4 text-xs text-gray-500">
                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-green-500"></span> resistiu</span>
                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-indigo-500"></span> cedeu</span>
            </div>
        </div>

        <!-- Registros -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold text-gray-500">Registros</h3>

                <button
                    type="button"
                    wire:click="toggleOrder"
                    class="inline-flex items-center justify-center w-8 h-8 text-gray-600 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                    title="{{ $order === 'asc' ? 'Mais antigos primeiro (clique para inverter)' : 'Mais recentes primeiro (clique para inverter)' }}"
                    aria-label="Inverter a ordem cronológica"
                >
                    <svg class="w-4 h-4 transition-transform {{ $order === 'desc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h13M3 8h9M3 12h5m6 4 4 4m0 0 4-4m-4 4V8" />
                    </svg>
                </button>
            </div>

            @foreach ($this->groupedLogs as $date => $dayLogs)
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 mb-2">
                        {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d \d\e F') }}
                    </h4>

                    <div class="space-y-3">
                        @foreach ($dayLogs as $log)
                            @php
                                $resisted = $log->outcome === CompulsionLog::OUTCOME_RESISTED;
                                $before = $log->feelings->where('pivot.moment', CompulsionLog::MOMENT_BEFORE);
                                $after = $log->feelings->where('pivot.moment', CompulsionLog::MOMENT_AFTER);
                            @endphp
                            <div
                                wire:key="compulsion-log-{{ $log->id }}"
                                class="{{ $resisted ? 'bg-green-50 border-green-500' : 'bg-indigo-50 border-indigo-500' }} shadow-sm sm:rounded-lg p-4 border-l-4"
                                x-data="{ expanded: false }"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-medium text-gray-800">{{ $log->occurred_at->format('H:i') }}</span>
                                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $resisted ? 'bg-green-700' : 'bg-indigo-700' }} text-white">{{ $resisted ? 'Resistiu' : 'Cedeu' }}</span>
                                            <span class="text-xs text-gray-600">{{ $log->compulsion->name }}</span>
                                            <span class="text-xs text-gray-500">vontade {{ $log->urge_intensity }}/10</span>
                                            @if ($log->duration_minutes)
                                                <span class="text-xs text-gray-500">{{ $log->duration_minutes }} min</span>
                                            @endif
                                        </div>
        
                                        <div class="flex flex-wrap items-center gap-1 mt-2 text-xs">
                                            @foreach ($before as $feeling)
                                                <span class="px-2 py-0.5 rounded-full bg-white/80 text-gray-700 ring-1 ring-black/5">{{ $feeling->name }}</span>
                                            @endforeach
                                            <span class="text-gray-400 px-1" aria-label="depois">→</span>
                                            @foreach ($after as $feeling)
                                                <span class="px-2 py-0.5 rounded-full bg-white/80 text-gray-700 ring-1 ring-black/5">{{ $feeling->name }}</span>
                                            @endforeach
                                        </div>
        
                                        <p class="text-sm text-gray-600 mt-2" x-show="!expanded">
                                            {{ \Illuminate\Support\Str::limit($log->trigger, 120) }}
                                        </p>
        
                                        <div x-show="expanded" class="mt-2 space-y-2 text-sm text-gray-600">
                                            <p><span class="font-medium text-gray-700">Situação:</span> {{ $log->trigger }}</p>
                                            @if ($log->automatic_thought)
                                                <p><span class="font-medium text-gray-700">Pensamento:</span> {{ $log->automatic_thought }}</p>
                                            @endif
                                            @if ($log->coping_strategy)
                                                <p><span class="font-medium text-gray-700">{{ $resisted ? 'O que ajudou:' : 'O que poderia ter feito:' }}</span> {{ $log->coping_strategy }}</p>
                                            @endif
                                            @if ($log->notes)
                                                <p><span class="font-medium text-gray-700">Observações:</span> {{ $log->notes }}</p>
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
            @endforeach

            <div class="mt-4">
                {{ $this->logs->links() }}
            </div>
        </div>
    @endif
</div>
