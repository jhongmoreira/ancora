<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);
});

test('stores a push subscription for the authenticated user', function () {
    $this->actingAs($this->user)->postJson('/push-subscriptions', [
        'endpoint' => 'https://push.example.com/abc',
        'keys' => ['p256dh' => 'key', 'auth' => 'token'],
    ])->assertNoContent();

    expect($this->user->pushSubscriptions()->count())->toBe(1);
});

test('removes a push subscription for the authenticated user', function () {
    $this->user->updatePushSubscription('https://push.example.com/abc', 'key', 'token', 'aes128gcm');

    $this->actingAs($this->user)->deleteJson('/push-subscriptions', [
        'endpoint' => 'https://push.example.com/abc',
    ])->assertNoContent();

    expect($this->user->pushSubscriptions()->count())->toBe(0);
});

test('guests cannot manage push subscriptions', function () {
    $this->postJson('/push-subscriptions', [
        'endpoint' => 'https://push.example.com/abc',
        'keys' => ['p256dh' => 'key', 'auth' => 'token'],
    ])->assertUnauthorized();
});
