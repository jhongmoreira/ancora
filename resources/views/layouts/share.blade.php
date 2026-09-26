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
        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white border-b border-gray-100">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {{-- No celular: marca na 1ª linha e links na 2ª, ambos centralizados. A partir de sm, tudo numa linha à esquerda. --}}
                    <div class="flex flex-wrap items-center gap-x-10 pt-3 sm:pt-0 sm:h-16">
                        <span class="w-full sm:w-auto text-center sm:text-left font-semibold text-gray-800">Âncora</span>
                        @isset($patient)
                            <div class="flex w-full sm:w-auto justify-center sm:justify-start sm:self-stretch sm:-my-px gap-x-5 sm:gap-x-8">
                                <x-nav-link :href="route('share.dashboard', $token)" :active="request()->routeIs('share.dashboard')" class="py-3 sm:py-0">
                                    Dashboard
                                </x-nav-link>
                                <x-nav-link :href="route('share.history', $token)" :active="request()->routeIs('share.history')" class="py-3 sm:py-0">
                                    Histórico
                                </x-nav-link>
                                <x-nav-link :href="route('share.compulsions', $token)" :active="request()->routeIs('share.compulsions')" class="py-3 sm:py-0">
                                    Compulsões
                                </x-nav-link>
                            </div>
                        @endisset
                    </div>
                </div>
            </nav>

            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}

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
                Criado com ❤️ por Jhonathan Moreira &amp; Claude &middot; {{ date('Y') }}
            </footer>
        </div>
    </body>
</html>
