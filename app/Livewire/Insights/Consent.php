<?php

namespace App\Livewire\Insights;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Status e revogação do consentimento para o uso da IA (docs/15, seção 4).
 * O consentimento é dado no modal da tela de Insights.
 */
class Consent extends Component
{
    public function revoke(): void
    {
        Auth::user()->patient->update(['ai_consent_at' => null]);
    }

    public function render()
    {
        return view('livewire.insights.consent', ['patient' => Auth::user()->patient]);
    }
}
