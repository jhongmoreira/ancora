<?php

namespace App\Livewire\Concerns;

use App\Models\Patient;
use App\Models\ShareLink;
use Livewire\Attributes\Locked;

/**
 * Revalida o link compartilhado a cada requisição do Livewire na visão da
 * psicóloga ($readOnly=true). O middleware share.access só protege a carga
 * inicial da página; as interações (filtros, período, ordem) vão direto para
 * /livewire/update e, sem isso, continuariam retornando dados depois de o
 * link ser revogado ou expirar — docs/13.
 */
trait GuardsSharedAccess
{
    #[Locked]
    public ?string $shareToken = null;

    public function hydrateGuardsSharedAccess(): void
    {
        if (! $this->readOnly || $this->hasValidSharedAccess()) {
            return;
        }

        // Troca o paciente por um modelo vazio: a ação/atualização desta
        // requisição ainda roda depois do hydrate, mas passa a consultar um
        // paciente sem registros, então nenhum dado é devolvido ao navegador.
        $this->patient = new Patient;

        $this->skipRender();
        $this->shareToken
            ? $this->redirectRoute('share.pin', ['token' => $this->shareToken])
            : $this->redirect('/');
    }

    protected function hasValidSharedAccess(): bool
    {
        if (! $this->shareToken || ! session()->get("share_access.{$this->shareToken}")) {
            return false;
        }

        $link = ShareLink::where('token', $this->shareToken)->first();

        return $link
            && ! $link->isExpired()
            && $link->patient_id === $this->patient?->id;
    }
}
