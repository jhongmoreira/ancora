<div class="bg-white shadow sm:rounded-lg p-6 max-w-xl">
    <div class="flex flex-wrap gap-4 items-end mb-6">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Período</label>
            <select wire:model.live="period" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="7d">Últimos 7 dias</option>
                <option value="30d">Últimos 30 dias</option>
                <option value="month">Este mês</option>
                <option value="all">Todo o histórico</option>
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
    </div>

    <p class="text-sm text-gray-600 mb-6">
        <span class="font-semibold">{{ $this->count }}</span>
        {{ Str::plural('registro', $this->count) }} encontrado(s) neste período.
    </p>

    @if ($this->count === 0)
        <p class="text-sm text-gray-400">Selecione um período com registros para poder exportar.</p>
    @else
        <div class="flex gap-3">
            <a
                href="{{ route('reports.pdf', ['from' => $from, 'to' => $to]) }}"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
            >
                Exportar PDF
            </a>
            <a
                href="{{ route('reports.excel', ['from' => $from, 'to' => $to]) }}"
                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50"
            >
                Exportar Excel
            </a>
        </div>
    @endif
</div>
