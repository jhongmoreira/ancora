@props([
    'patient',
    // Nome exibido: a visão compartilhada passa as iniciais (docs/13); sem ele, usa o nome completo.
    'name' => null,
    'expiresAt' => null,
])

{{-- Faixa compacta abaixo do título da página (hoje usada no layout compartilhado). No celular os rótulos somem e ficam só os ícones. --}}
<div {{ $attributes->merge(['class' => 'mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm']) }}>
    <span class="inline-flex items-center gap-1.5 text-gray-800">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
        </svg>
        <span class="sr-only sm:not-sr-only text-gray-500">Paciente</span>
        <span class="font-medium">{{ $name ?? $patient->full_name }}</span>
    </span>

    @if ($patient->professional)
        <span class="inline-flex items-center gap-1.5 text-gray-800">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
            </svg>
            <span class="sr-only sm:not-sr-only text-gray-500">Psicóloga(o)</span>
            <span class="font-medium">{{ $patient->professional->name }}</span>
        </span>
    @endif

    @if ($expiresAt)
        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/15">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            Expira em {{ $expiresAt->format('d/m/Y \à\s H:i') }}
        </span>
    @endif
</div>
