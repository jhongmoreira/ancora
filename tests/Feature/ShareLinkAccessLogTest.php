<?php

use App\Livewire\ShareLinkManager;
use App\Models\User;
use App\Services\ShareLinkService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);

    ['link' => $this->link, 'pin' => $this->pin] = app(ShareLinkService::class)->generate(
        $this->user->patient,
        now()->addDay()
    );
});

test('logs ip and user agent on successful pin verification', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36'])
        ->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $access = $this->user->patient->shareLinkAccesses()->first();

    expect($access)->not->toBeNull()
        ->and($access->ip_address)->not->toBeNull()
        ->and($access->device_label)->toBe('Chrome em Windows');
});

test('does not log an access attempt for a wrong pin', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => '000000']);

    expect($this->user->patient->shareLinkAccesses()->count())->toBe(0);
});

test('access history survives revoking the link', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    app(ShareLinkService::class)->revoke($this->user->patient);

    expect($this->user->patient->shareLinkAccesses()->count())->toBe(1);
});

test('patient can see the access history on the share management page', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    Livewire::actingAs($this->user)
        ->test(ShareLinkManager::class)
        ->assertSee('Dispositivo desconhecido')
        ->assertDontSee('Ninguém acessou');
});
