<div>
    <div class="bg-white shadow-sm sm:rounded-lg px-4 py-3 mb-6 flex flex-wrap items-center gap-3">
        <label for="dashboard-period" class="sr-only">Período</label>
        <select id="dashboard-period" wire:model.live="period" class="w-full sm:w-auto text-sm py-1.5 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="7d">Últimos 7 dias</option>
            <option value="15d">Última quinzena</option>
            <option value="30d">Últimos 30 dias</option>
            <option value="month">Este mês</option>
        </select>

        <div class="flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-200 text-xs font-medium"
                title="Dias com pelo menos um registro no período selecionado"
            >
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                {{ $consistency['daysWithLogs'] }}/{{ $consistency['totalDays'] }} dias
            </span>

            @if ($streak['current'] > 0)
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200 text-xs font-medium"
                    title="Dias seguidos registrando (sequência atual)"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z" />
                    </svg>
                    {{ $streak['current'] }} {{ Str::plural('dia', $streak['current']) }} seguido{{ $streak['current'] > 1 ? 's' : '' }}
                </span>
            @endif
        </div>

        @unless ($readOnly)
            {{-- No celular os botões vão para uma linha própria, com "Novo registro" ocupando a largura. --}}
            <div class="flex w-full sm:w-auto sm:ms-auto items-center gap-2">
                <a
                    href="{{ route('compulsions.log') }}"
                    wire:navigate
                    class="inline-flex items-center justify-center w-9 h-9 flex-shrink-0 rounded-md border border-gray-300 text-gray-500 hover:text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition"
                    title="Registrar compulsão"
                    aria-label="Registrar compulsão"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                    </svg>
                </a>
                <a href="{{ route('emotion-logs.create') }}" wire:navigate class="flex-1 sm:flex-none inline-flex items-center justify-center h-9 px-4 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                    Novo registro
                </a>
            </div>
        @endunless
    </div>

    @if ($chartData['total'] === 0)
        <div class="bg-white shadow sm:rounded-lg p-10 text-center text-gray-500">
            <p>Nenhum registro no período selecionado ainda.</p>
            @unless ($readOnly)
                <a href="{{ route('emotion-logs.create') }}" wire:navigate class="text-indigo-600 underline text-sm mt-2 inline-block">Fazer seu primeiro registro</a>
            @endunless
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 sm:p-6">
                <h3 class="text-sm font-semibold text-indigo-900 mb-4">📋 Resumo do período</h3>
                <p class="text-sm text-indigo-900">{{ $summary }}</p>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Palavras mais comuns nas situações</h3>
                <p class="text-xs text-gray-400 mb-4">Termos que mais aparecem nas situações associadas a humor desagradável — possíveis gatilhos recorrentes.</p>

                @if (empty($triggers['words']))
                    <p class="text-sm text-gray-400">Sem registros de humor desagradável suficientes no período pra identificar padrões.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($triggers['words'] as $word => $count)
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-red-50 text-red-700 text-xs font-medium">
                                {{ $word }} <span class="text-red-400">({{ $count }})</span>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Velocidade de recuperação emocional</h3>
            <p class="text-xs text-gray-400 mb-4">Tempo médio entre um registro de humor desagradável e o próximo registro agradável ou neutro.</p>

            @if (is_null($recovery['average']))
                <p class="text-sm text-gray-400">
                    @if ($recovery['analyzed'] === 0)
                        Nenhum registro de humor desagradável no período selecionado.
                    @else
                        Ainda não há um registro agradável/neutro depois {{ $recovery['analyzed'] === 1 ? 'do registro desagradável' : 'dos '.$recovery['analyzed'].' registros desagradáveis' }} do período — pode levar um tempo pra aparecer.
                    @endif
                </p>
            @else
                <p class="text-2xl font-semibold text-gray-800">
                    {{ $this->formatRecoveryDuration($recovery['average']) }}
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    Média calculada sobre {{ $recovery['count'] }} {{ Str::plural('episódio', $recovery['count']) }} de humor desagradável com recuperação identificada
                    (de {{ $recovery['analyzed'] }} no período).
                </p>
            @endif
        </div>
    @endif

    <div wire:ignore x-data="ancoraDashboard(@js($chartData))" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6 lg:col-span-2">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Evolução por humor</h3>
            <div class="relative h-56 sm:h-64">
                <canvas x-ref="evolutionCanvas"></canvas>
            </div>
        </div>

        <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Sentimentos mais frequentes</h3>
            <canvas x-ref="feelingsCanvas" height="200"></canvas>
        </div>

        <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Distribuição de humor</h3>
            <canvas x-ref="distributionCanvas" height="200"></canvas>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6 mt-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-1">Quando o humor desagradável mais aparece</h3>
        <p class="text-xs text-gray-400 mb-4">Concentração de registros de humor desagradável por dia da semana e período do dia.</p>

        @if ($heatmap['total'] === 0)
            <p class="text-sm text-gray-400">Nenhum registro de humor desagradável no período selecionado.</p>
        @else
            <div class="overflow-x-auto">
                <table class="text-xs w-full">
                    <thead>
                        <tr>
                            <th class="text-left font-medium text-gray-400 pb-2 pr-2"></th>
                            @foreach ($heatmap['periods'] as $period)
                                <th class="text-center font-medium text-gray-400 pb-2 px-1">{{ $period }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($heatmap['days'] as $day)
                            <tr>
                                <td class="pr-2 text-gray-500 font-medium">{{ $day }}</td>
                                @foreach ($heatmap['periods'] as $period)
                                    @php $count = $heatmap['grid'][$day][$period]; @endphp
                                    <td class="px-1 py-1">
                                        <div
                                            class="h-8 rounded flex items-center justify-center text-white font-medium"
                                            style="background-color: rgba(220, 38, 38, {{ $count === 0 ? 0.06 : min(0.15 + ($count / $heatmap['max']) * 0.85, 1) }})"
                                            title="{{ $day }} · {{ $period }}: {{ $count }} registro(s)"
                                        >
                                            <span class="{{ $count === 0 ? 'text-gray-300' : 'text-white' }}">{{ $count ?: '' }}</span>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
