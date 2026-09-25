<x-share-layout>
    <div class="max-w-sm mx-auto bg-white shadow sm:rounded-lg p-6 mt-12">
        <h1 class="text-lg font-semibold text-gray-800 mb-1">Acesso compartilhado</h1>
        <p class="text-sm text-gray-500 mb-6">Digite o PIN de 6 dígitos que você recebeu para ver os dados.</p>

        @if (session('status'))
            <div class="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-700">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('share.verify', $token) }}">
            @csrf

            <x-input-label for="pin" value="PIN" />
            <x-text-input
                id="pin"
                name="pin"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                autofocus
                class="mt-1 block w-full text-center text-2xl tracking-[0.5em] font-mono"
            />
            <x-input-error :messages="$errors->get('pin')" class="mt-2" />

            <x-primary-button class="mt-4 w-full justify-center">
                Acessar
            </x-primary-button>
        </form>
    </div>
</x-share-layout>
