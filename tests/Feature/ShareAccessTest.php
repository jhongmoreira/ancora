<?php

use App\Models\MoodCategory;
use App\Models\User;
use App\Services\ShareLinkService;
use Database\Seeders\MoodCatalogSeeder;

beforeEach(function () {
    $this->seed(MoodCatalogSeeder::class);

    $this->user = User::factory()->create();
    $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);

    $this->user->patient->emotionLogs()->create([
        'mood_category_id' => MoodCategory::first()->id,
        'occurred_at' => now(),
        'situation' => 'Situação sigilosa do paciente',
        'action' => 'Ação',
    ]);

    ['link' => $this->link, 'pin' => $this->pin] = app(ShareLinkService::class)->generate(
        $this->user->patient,
        now()->addDay()
    );
});

test('shows the pin form for a valid token', function () {
    $this->get(route('share.pin', $this->link->token))
        ->assertOk()
        ->assertSee('PIN');
});

test('shows an invalid page for an unknown token', function () {
    $this->get(route('share.pin', 'token-que-nao-existe'))
        ->assertOk()
        ->assertSee('inválido');
});

test('correct pin grants access to the dashboard and history', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin])
        ->assertRedirect(route('share.dashboard', $this->link->token));

    $this->get(route('share.dashboard', $this->link->token))
        ->assertOk()
        ->assertSee('Fulano de Tal');

    $this->get(route('share.history', $this->link->token))
        ->assertOk()
        ->assertSee('Situação sigilosa do paciente');
});

test('wrong pin is rejected', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => '000000'])
        ->assertSessionHasErrors('pin');

    $this->get(route('share.dashboard', $this->link->token))
        ->assertRedirect(route('share.pin', $this->link->token));
});

test('pin is rate limited after 3 wrong attempts', function () {
    foreach (range(1, 3) as $_) {
        $this->post(route('share.verify', $this->link->token), ['pin' => '000000']);
    }

    $response = $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $response->assertSessionHasErrors('pin');
    $this->get(route('share.dashboard', $this->link->token))
        ->assertRedirect(route('share.pin', $this->link->token));
});

test('expired link shows the invalid page even with the correct pin previously verified', function () {
    $this->withSession(["share_access.{$this->link->token}" => true]);

    $this->link->update(['expires_at' => now()->subMinute()]);

    $this->get(route('share.dashboard', $this->link->token))
        ->assertRedirect(route('share.pin', $this->link->token));
});

test('history view is read only and cannot delete records', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $log = $this->user->patient->emotionLogs()->first();

    \Livewire\Livewire::test(\App\Livewire\EmotionLog\History::class, [
        'patient' => $this->user->patient,
        'readOnly' => true,
    ])->call('delete', $log->id);

    expect($this->user->patient->emotionLogs()->count())->toBe(1);
});

test('regenerating the link invalidates the old token immediately', function () {
    $oldToken = $this->link->token;

    app(ShareLinkService::class)->generate($this->user->patient, now()->addDay());

    $this->get(route('share.pin', $oldToken))
        ->assertOk()
        ->assertSee('inválido');
});
