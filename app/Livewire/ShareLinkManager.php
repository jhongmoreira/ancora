<?php

namespace App\Livewire;

use App\Services\ShareLinkService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ShareLinkManager extends Component
{
    public string $expiresAt = '';

    public ?string $generatedPin = null;

    public ?string $generatedUrl = null;

    public function mount(): void
    {
        $this->expiresAt = app(ShareLinkService::class)->defaultExpiration()->format('Y-m-d\TH:i');
    }

    protected function rules(): array
    {
        return [
            'expiresAt' => ['required', 'date', 'after:now'],
        ];
    }

    #[Computed]
    public function link()
    {
        // Consulta direta (não a propriedade dinâmica ->shareLink) para nunca
        // arriscar reaproveitar um relacionamento "nulo" cacheado no model do
        // Eloquent de uma leitura anterior nesta mesma requisição.
        return Auth::user()->patient->shareLink()->first();
    }

    #[Computed]
    public function accessLogs()
    {
        return Auth::user()->patient->shareLinkAccesses()->latest('accessed_at')->limit(20)->get();
    }

    public function generate(): void
    {
        $validated = $this->validate();

        $result = app(ShareLinkService::class)->generate(
            Auth::user()->patient,
            \Illuminate\Support\Carbon::parse($validated['expiresAt'])
        );

        $this->generatedPin = $result['pin'];
        $this->generatedUrl = route('share.pin', $result['link']->token);

        unset($this->link);

        $this->dispatch('share-link-generated', text: $this->clipboardText());
    }

    public function clipboardText(): string
    {
        return "Acompanhe meus registros no Âncora:\n{$this->generatedUrl}\n\nPIN de acesso: {$this->generatedPin}";
    }

    public function revoke(): void
    {
        app(ShareLinkService::class)->revoke(Auth::user()->patient);

        $this->generatedPin = null;
        $this->generatedUrl = null;
        unset($this->link);
    }

    public function render()
    {
        return view('livewire.share-link-manager');
    }
}
