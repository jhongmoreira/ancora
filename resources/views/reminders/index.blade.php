<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Lembretes') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6" x-data="ancoraPushSubscription()">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Notificações push</h3>

                <template x-if="!supported">
                    <p class="text-sm text-gray-500">Seu navegador não tem suporte a notificações push.</p>
                </template>

                <template x-if="supported">
                    <div>
                        <p class="text-sm text-gray-500 mb-3" x-show="!subscribed">
                            Ative as notificações para receber lembretes mesmo com o navegador fechado.
                        </p>
                        <p class="text-sm text-green-600 mb-3" x-show="subscribed">
                            Notificações ativadas neste dispositivo.
                        </p>

                        <button
                            type="button"
                            x-show="!subscribed"
                            x-on:click="subscribe"
                            :disabled="loading"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Ativar notificações
                        </button>

                        <button
                            type="button"
                            x-show="subscribed"
                            x-on:click="unsubscribe"
                            :disabled="loading"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 disabled:opacity-50"
                        >
                            Desativar notificações
                        </button>

                        <p class="text-xs text-gray-400 mt-3">
                            No iPhone, adicione o Âncora à tela de início (compartilhar → "Adicionar à Tela de Início") para poder receber notificações.
                        </p>
                    </div>
                </template>
            </div>

            <livewire:reminder-manager />
        </div>
    </div>
</x-app-layout>
