<?php

use App\Livewire\ShareLinkManager;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);
});

test('generates a share link with the default expiration', function () {
    Livewire::actingAs($this->user)
        ->test(ShareLinkManager::class)
        ->assertSet('expiresAt', now()->setTime(23, 59)->format('Y-m-d\TH:i'))
        ->call('generate')
        ->assertHasNoErrors();

    $link = $this->user->patient->shareLink()->first();

    expect($link)->not->toBeNull()
        ->and($link->expires_at->format('H:i'))->toBe('23:59');
});

test('regenerating replaces the previous token and pin', function () {
    $component = Livewire::actingAs($this->user)->test(ShareLinkManager::class);

    $component->call('generate');
    $firstToken = $this->user->patient->shareLink->token;

    $component->call('generate');
    $secondToken = $this->user->patient->fresh()->shareLink->token;

    expect($secondToken)->not->toBe($firstToken)
        ->and($this->user->patient->shareLink()->count())->toBe(1);
});

test('expiration must be in the future', function () {
    Livewire::actingAs($this->user)
        ->test(ShareLinkManager::class)
        ->set('expiresAt', now()->subHour()->format('Y-m-d\TH:i'))
        ->call('generate')
        ->assertHasErrors('expiresAt');

    expect($this->user->patient->shareLink)->toBeNull();
});

test('dispatches a browser event with the link and pin for clipboard copy', function () {
    $component = Livewire::actingAs($this->user)
        ->test(ShareLinkManager::class)
        ->call('generate');

    $link = $this->user->patient->shareLink;
    $pin = $component->get('generatedPin');

    $component->assertDispatched('share-link-generated', function (string $name, array $params) use ($link, $pin) {
        return str_contains($params['text'], route('share.pin', $link->token))
            && str_contains($params['text'], $pin);
    });
});

test('can revoke the active link', function () {
    $component = Livewire::actingAs($this->user)->test(ShareLinkManager::class);
    $component->call('generate');

    expect($this->user->patient->shareLink)->not->toBeNull();

    $component->call('revoke');

    expect($this->user->patient->fresh()->shareLink)->toBeNull();
});
