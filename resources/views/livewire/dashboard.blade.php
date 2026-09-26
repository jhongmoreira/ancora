<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Período</label>
                <select wire:model.live="period" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="7d">Últimos 7 dias</option>
                    <option value="15d">Última quinzena</option>
                    <option value="30d">Últimos 30 dias</option>
                    <option value="month">Este mês</option>
                </select>
            </div>

            <div>
                <span class="block text-xs mb-1 invisible" aria-hidden="true">Período</span>
                <div class="flex items-center gap-2 h-[38px]">
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 text-xs font-medium"
                        title="Dias com pelo menos um registro no período selecionado"
                    >
                        📅 {{ $consistency['daysWithLogs'] }}/{{ $consistency['totalDays'] }} dias
                    </span>

                    @if ($streak['current'] > 0)
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-orange-50 text-orange-700 text-xs font-medium"
                            title="Dias seguidos registrando (sequência atual)"
                        >
                            🔥 {{ $streak['current'] }} {{ Str::plural('dia', $streak['current']) }} seguido{{ $streak['current'] > 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @unless ($readOnly)
            <a href="{{ route('emotion-logs.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Novo registro
            </a>
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
            <canvas x-ref="evolutionCanvas" height="90"></canvas>
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
