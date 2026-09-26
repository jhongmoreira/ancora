<div>
    <h3 class="text-sm font-semibold text-gray-700">Insights com IA</h3>

    @if ($patient->hasAiConsent())
        <p class="mt-1 text-sm text-gray-500">
            Você autorizou o envio dos seus registros (anonimizados) ao Gemini, do Google, em {{ $patient->ai_consent_at->format('d/m/Y \à\s H:i') }}.
            Ao retirar a autorização, nenhum novo relatório será gerado; os relatórios já gerados continuam salvos.
        </p>
        <button type="button" wire:click="revoke" wire:confirm="Retirar a autorização para o uso da IA?" class="mt-3 text-sm text-red-600 hover:text-red-800">
            Retirar autorização
        </button>
    @else
        <p class="mt-1 text-sm text-gray-500">
            Você não autorizou o uso da IA. A autorização é pedida na tela <a href="{{ route('insights.index') }}" wire:navigate class="text-indigo-600 underline">Insights</a>, antes do primeiro relatório.
        </p>
    @endif
</div>
