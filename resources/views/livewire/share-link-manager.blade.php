<div class="bg-white shadow sm:rounded-lg p-6 max-w-xl space-y-6">
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-1">Compartilhar com a psicóloga</h3>
        <p class="text-sm text-gray-500">
            Gere um link temporário para sua psicóloga acompanhar seu dashboard e histórico (somente leitura),
            protegido por um PIN de 6 dígitos.
        </p>
    </div>

    @if ($generatedPin)
        <div class="rounded-md border-2 border-indigo-200 bg-indigo-50 p-4 space-y-2">
            <p class="text-sm font-semibold text-indigo-800">Link gerado! Anote o PIN agora — ele não será mostrado de novo.</p>

            <div>
                <label class="block text-xs font-medium text-indigo-700">Link</label>
                <input type="text" readonly value="{{ $generatedUrl }}" onclick="this.select()" class="mt-1 block w-full text-sm border-indigo-300 rounded-md bg-white" />
            </div>

            <div>
                <label class="block text-xs font-medium text-indigo-700">PIN</label>
                <input type="text" readonly value="{{ $generatedPin }}" onclick="this.select()" class="mt-1 block w-40 text-lg tracking-widest font-mono border-indigo-300 rounded-md bg-white" />
            </div>

            <p class="text-xs text-indigo-600">Envie o link e o PIN pelos canais que preferir (WhatsApp, e-mail...), de preferência separadamente.</p>
        </div>
    @endif

    @if ($this->link && ! $generatedPin)
        <div class="rounded-md border border-gray-200 p-4">
            @if ($this->link->isExpired())
                <p class="text-sm text-red-600">Seu último link expirou em {{ $this->link->expires_at->format('d/m/Y H:i') }}.</p>
            @else
                <p class="text-sm text-gray-600">
                    Link ativo, expira em <span class="font-medium">{{ $this->link->expires_at->format('d/m/Y H:i') }}</span>.
                </p>
                <p class="text-xs text-gray-400 mt-1">Por segurança, o PIN só é exibido no momento em que é gerado.</p>
            @endif

            <button type="button" wire:click="revoke" wire:confirm="Revogar o acesso da psicóloga a este link?" class="text-xs text-red-600 hover:text-red-800 mt-2">
                Revogar acesso agora
            </button>
        </div>
    @endif

    <form wire:submit="generate" class="space-y-3 pt-4 border-t border-gray-100">
        <div>
            <x-input-label for="expiresAt" value="Expira em" />
            <input type="datetime-local" wire:model="expiresAt" id="expiresAt" class="mt-1 block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            <x-input-error :messages="$errors->get('expiresAt')" class="mt-2" />
        </div>

        <x-primary-button>
            {{ $this->link ? 'Gerar novo link (substitui o atual)' : 'Gerar link' }}
        </x-primary-button>
    </form>

    <div class="pt-6 border-t border-gray-100">
        <h4 class="text-sm font-semibold text-gray-700 mb-3">Histórico de acessos</h4>

        @if ($this->accessLogs->isEmpty())
            <p class="text-sm text-gray-400">Ninguém acessou seus dados compartilhados ainda.</p>
        @else
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($this->accessLogs as $access)
                    <li class="py-2 flex items-center justify-between">
                        <div>
                            <span class="text-gray-700">{{ $access->accessed_at->format('d/m/Y H:i') }}</span>
                            <span class="text-gray-400 ml-2">{{ $access->device_label }}</span>
                        </div>
                        <span class="text-xs text-gray-400 font-mono">{{ $access->ip_address }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
