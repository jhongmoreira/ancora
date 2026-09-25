<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Período</label>
            <select wire:model.live="period" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="7d">Últimos 7 dias</option>
                <option value="30d">Últimos 30 dias</option>
                <option value="month">Este mês</option>
            </select>
        </div>

        <a href="{{ route('emotion-logs.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
            Novo registro
        </a>
    </div>

    @if ($chartData['total'] === 0)
        <div class="bg-white shadow sm:rounded-lg p-10 text-center text-gray-500">
            <p>Nenhum registro no período selecionado ainda.</p>
            <a href="{{ route('emotion-logs.create') }}" wire:navigate class="text-indigo-600 underline text-sm mt-2 inline-block">Fazer seu primeiro registro</a>
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
</div>
