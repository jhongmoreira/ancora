<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ config('app.name') }} — Acesso compartilhado</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        {{-- Com paciente: fecha a página aberta quando o link expira, é revogado ou o prazo do PIN acaba (docs/13). --}}
        <div
            class="min-h-screen bg-gray-100"
            @isset($patient)
                x-data="ancoraShareGuard(@js([
                    'statusUrl' => route('share.status', $token),
                    'lockUrl' => route('share.pin', $token),
                    'expiresInSeconds' => max(0, (int) now()->diffInSeconds($expiresAt)),
                    'pinRemainingSeconds' => app(\App\Services\ShareAccessSession::class)->remainingSeconds($token),
                    'pinValidityMinutes' => app(\App\Services\ShareAccessSession::class)->validityMinutes(),
                    'expiresAtLabel' => $expiresAt->format('d/m \à\s H:i'),
                ]))"
            @endisset
        >
            <nav class="bg-white border-b border-gray-100">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {{-- No celular: marca na 1ª linha e links na 2ª, ambos centralizados. A partir de sm, tudo numa linha à esquerda. --}}
                    <div class="relative flex flex-wrap items-center gap-x-10 pt-3 sm:pt-0 sm:h-16">
                        <div class="flex w-full sm:w-auto justify-center sm:justify-start">
                            <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                        </div>
                        @isset($patient)
                            <div class="flex w-full sm:w-auto [justify-content:safe_center] sm:justify-start overflow-x-auto sm:overflow-visible sm:self-stretch sm:-my-px gap-x-4 sm:gap-x-8">
                                <x-nav-link :href="route('share.dashboard', $token)" :active="request()->routeIs('share.dashboard')" class="py-3 sm:py-0 whitespace-nowrap">
                                    Dashboard
                                </x-nav-link>
                                <x-nav-link :href="route('share.history', $token)" :active="request()->routeIs('share.history')" class="py-3 sm:py-0 whitespace-nowrap">
                                    Histórico
                                </x-nav-link>
                                <x-nav-link :href="route('share.compulsions', $token)" :active="request()->routeIs('share.compulsions')" class="py-3 sm:py-0 whitespace-nowrap">
                                    Compulsões
                                </x-nav-link>
                                <x-nav-link :href="route('share.insights', $token)" :active="request()->routeIs('share.insights')" class="py-3 sm:py-0 whitespace-nowrap">
                                    Insights
                                </x-nav-link>
                            </div>

                            {{-- No celular fica no canto da linha da marca, só com o ícone. --}}
                            <form method="POST" action="{{ route('share.logout', $token) }}" class="absolute right-0 top-3 sm:static sm:ml-auto">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1.5 h-9 px-2 text-sm font-medium text-gray-500 rounded-md hover:text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    title="Encerrar o acesso compartilhado"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                    </svg>
                                    <span class="sr-only sm:not-sr-only">Sair</span>
                                </button>
                            </form>
                        @endisset
                    </div>
                </div>
            </nav>

            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                {{ $header }}
                            </div>

                            @isset($patient)
                                {{-- Contagem até a página ser fechada (prazo do PIN ou expiração do link, o que vier primeiro). --}}
                                <span
                                    role="timer"
                                    class="inline-flex flex-shrink-0 items-center gap-1 pt-1 text-xs text-gray-400 tabular-nums"
                                    x-bind:title="remainingTitle"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                    <span>Encerra em <span x-text="remainingLabel"></span></span>
                                </span>
                            @endisset
                        </div>

                        @isset($patient)
                            <x-patient-info-bar :patient="$patient" :name="$patient->initials" :expires-at="$expiresAt" />
                        @endisset
                    </div>
                </header>
            @endisset

            <main class="py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>

            <footer class="text-center text-xs text-gray-400 py-6">
                Criado com Claude &middot; {{ date('Y') }}
            </footer>
        </div>
    </body>
</html>
