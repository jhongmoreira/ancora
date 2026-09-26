{{-- Código de um registro citado pelo relatório (ex.: "E12"); ao tocar, mostra o registro original. Espera $id e $evidence. --}}
@php
    $record = $evidence[$id] ?? null;
@endphp
<span class="relative inline-block" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    <button
        type="button"
        x-on:click="open = !open"
        @class([
            'text-[11px] font-mono font-medium px-1.5 py-0.5 rounded ring-1 ring-inset',
            'bg-white text-gray-600 ring-gray-200 hover:ring-indigo-300 hover:text-indigo-700' => $record,
            'bg-gray-50 text-gray-400 ring-gray-100 cursor-default' => ! $record,
        ])
        @if (! $record) disabled title="Registro não encontrado (pode ter sido excluído)" @endif
        :aria-expanded="open"
    >{{ $id }}</button>

    @if ($record)
        <span
            x-show="open"
            x-cloak
            x-transition.opacity
            class="absolute left-0 z-20 mt-1 w-72 max-w-[80vw] rounded-lg bg-white shadow-lg ring-1 ring-gray-200 p-3 text-left"
            role="dialog"
        >
            <span class="block text-[11px] font-medium text-gray-500">{{ $record['when'] }}</span>
            <span class="block text-xs font-semibold text-gray-800">{{ $record['label'] }}</span>
            @foreach ($record['lines'] as $label => $line)
                <span class="mt-1.5 block text-xs text-gray-600"><span class="font-medium text-gray-700">{{ $label }}:</span> {{ $line }}</span>
            @endforeach
        </span>
    @endif
</span>
