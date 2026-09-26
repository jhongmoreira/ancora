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
                    <div class="flex justify-between h-16 items-center">
                        <div class="flex items-center gap-6">
                            <span class="font-semibold text-gray-800">Âncora</span>
                            @isset($patient)
                                <a href="{{ route('share.dashboard', $token) }}" class="text-sm text-gray-600 hover:text-gray-900">Dashboard</a>
                                <a href="{{ route('share.history', $token) }}" class="text-sm text-gray-600 hover:text-gray-900">Histórico</a>
                            @endisset
                        </div>
                        <span class="text-xs px-3 py-1 rounded-full bg-amber-100 text-amber-800 font-medium">
                            Acesso compartilhado — somente leitura
                        </span>
                    </div>
                </div>
            </nav>

            @isset($patient)
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
                    <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-gray-500">
                        <span>Paciente: <span class="font-medium text-gray-800">{{ $patient->initials }}</span></span>
                        @if ($patient->professional)
                            <span aria-hidden="true">·</span>
                            <span>Psicóloga(o): <span class="font-medium text-gray-800">{{ $patient->professional->name }}</span></span>
                        @endif
                        @isset($expiresAt)
                            <span aria-hidden="true">·</span>
                            <span class="inline-flex items-center gap-1 font-bold text-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Expira em {{ $expiresAt->format('d/m/Y \à\s H:i') }}
                            </span>
                        @endisset
                    </p>
                </div>
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
