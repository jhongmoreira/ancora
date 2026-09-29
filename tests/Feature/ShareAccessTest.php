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
        ->assertSee('F. T.')
        ->assertDontSee('Fulano de Tal');

    $this->get(route('share.history', $this->link->token))
        ->assertOk()
        ->assertSee('Situação sigilosa do paciente');
});

test('dashboard and history show the link expiration date', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $expiresLabel = $this->link->expires_at->format('d/m/Y');

    $this->get(route('share.dashboard', $this->link->token))
        ->assertOk()
        ->assertSee($expiresLabel);

    $this->get(route('share.history', $this->link->token))
        ->assertOk()
        ->assertSee($expiresLabel);
});

test('shared pages are not cached by the browser', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $response = $this->get(route('share.dashboard', $this->link->token))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('shared pages carry the guard that closes them when the link ends', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $html = $this->get(route('share.history', $this->link->token))
        ->assertOk()
        ->assertSee('ancoraShareGuard', false)
        ->assertSee('role="timer"', false)
        ->getContent();

    // A configuração vai como JSON escapado pelo @js; sem as barras invertidas, a URL aparece inteira.
    expect(str_replace('\\', '', $html))->toContain(route('share.status', $this->link->token));
});

test('status reports a valid link with the remaining time', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $this->getJson(route('share.status', $this->link->token))
        ->assertOk()
        ->assertJson(['valid' => true])
        ->assertJsonPath('remaining_seconds', fn ($seconds) => $seconds > 0 && $seconds <= 86400);
});

test('status is invalid without the pin verified in this session', function () {
    $this->getJson(route('share.status', $this->link->token))
        ->assertOk()
        ->assertExactJson(['valid' => false, 'remaining_seconds' => 0, 'pin_remaining_seconds' => 0]);
});

test('status becomes invalid once the link expires, is revoked or regenerated', function (string $how) {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    match ($how) {
        'expired' => $this->travel(2)->days(),
        'revoked' => $this->link->delete(),
        'regenerated' => app(ShareLinkService::class)->generate($this->user->patient, now()->addDay()),
    };

    $this->getJson(route('share.status', $this->link->token))
        ->assertOk()
        ->assertJson(['valid' => false]);
})->with(['expired', 'revoked', 'regenerated']);

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
    $this->withSession(["share_access.{$this->link->token}" => now()->getTimestamp()]);

    $this->link->update(['expires_at' => now()->subMinute()]);

    $this->get(route('share.dashboard', $this->link->token))
        ->assertRedirect(route('share.pin', $this->link->token));
});

test('asks for the pin again 60 minutes after it was entered', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $this->travel(61)->minutes();

    $this->get(route('share.dashboard', $this->link->token))
        ->assertRedirect(route('share.pin', $this->link->token));

    $this->get(route('share.pin', $this->link->token))
        ->assertOk()
        ->assertSee('o PIN vale por 60 minutos')
        ->assertSee('Digite o PIN');
});

test('navigating or reloading does not extend the pin validity', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $this->travel(40)->minutes();
    $this->get(route('share.dashboard', $this->link->token))->assertOk();
    $this->get(route('share.dashboard', $this->link->token))->assertOk();
    $this->getJson(route('share.status', $this->link->token))
        ->assertJson(['valid' => true, 'pin_remaining_seconds' => 20 * 60]);

    $this->travel(21)->minutes();
    $this->get(route('share.history', $this->link->token))
        ->assertRedirect(route('share.pin', $this->link->token));
    $this->getJson(route('share.status', $this->link->token))->assertJson(['valid' => false]);
});

test('entering the pin again restarts the validity', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $this->travel(61)->minutes();
    $this->get(route('share.dashboard', $this->link->token))->assertRedirect();

    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);
    $this->get(route('share.dashboard', $this->link->token))->assertOk();
});

test('open shared views stop returning data once the pin validity ends', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $view = \Livewire\Livewire::test(\App\Livewire\EmotionLog\History::class, [
        'patient' => $this->user->patient,
        'readOnly' => true,
        'shareToken' => $this->link->token,
    ]);

    $this->travel(40)->minutes();
    $view->call('toggleOrder')->assertNoRedirect();

    $this->travel(21)->minutes();
    $view->call('toggleOrder')
        ->assertRedirect(route('share.pin', $this->link->token));

    expect($view->effects)->not->toHaveKey('html')
        ->and($view->instance()->patient->exists)->toBeFalse();
});

test('pin validity is configurable', function () {
    config(['share.pin_validity_minutes' => 5]);

    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $this->travel(6)->minutes();

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

test('shared history lists logs newest first by default', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $this->user->patient->emotionLogs()->create([
        'mood_category_id' => MoodCategory::first()->id,
        'occurred_at' => now()->subDays(2),
        'situation' => 'Registro mais antigo',
        'action' => 'Ação',
    ]);

    \Livewire\Livewire::test(\App\Livewire\EmotionLog\History::class, [
        'patient' => $this->user->patient,
        'readOnly' => true,
        'shareToken' => $this->link->token,
    ])
        ->assertSet('order', 'desc')
        ->assertSeeInOrder(['Situação sigilosa do paciente', 'Registro mais antigo']);
});

test('regenerating the link invalidates the old token immediately', function () {
    $oldToken = $this->link->token;

    app(ShareLinkService::class)->generate($this->user->patient, now()->addDay());

    $this->get(route('share.pin', $oldToken))
        ->assertOk()
        ->assertSee('inválido');
});

test('open shared views stop returning data after the link is revoked', function (string $component) {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $view = \Livewire\Livewire::test($component, [
        'patient' => $this->user->patient,
        'readOnly' => true,
        'shareToken' => $this->link->token,
    ]);

    $view->set('period', '7d')->assertNoRedirect();

    app(ShareLinkService::class)->revoke($this->user->patient);

    $view->set('period', '30d')
        ->assertRedirect(route('share.pin', $this->link->token));

    expect($view->effects)->not->toHaveKey('html')
        ->and($view->instance()->patient->exists)->toBeFalse();
})->with([
    'dashboard' => \App\Livewire\Dashboard::class,
    'history' => \App\Livewire\EmotionLog\History::class,
    'compulsions' => \App\Livewire\Compulsion\History::class,
]);

test('open shared views stop returning data after the link expires', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $view = \Livewire\Livewire::test(\App\Livewire\EmotionLog\History::class, [
        'patient' => $this->user->patient,
        'readOnly' => true,
        'shareToken' => $this->link->token,
    ])->assertSee('Situação sigilosa do paciente');

    $this->link->update(['expires_at' => now()->subMinute()]);

    $view->call('toggleOrder')
        ->assertRedirect(route('share.pin', $this->link->token));

    expect($view->effects)->not->toHaveKey('html')
        ->and($view->instance()->patient->exists)->toBeFalse();
});

test('shared views cannot be switched out of read only mode', function () {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    \Livewire\Livewire::test(\App\Livewire\EmotionLog\History::class, [
        'patient' => $this->user->patient,
        'readOnly' => true,
        'shareToken' => $this->link->token,
    ])->set('readOnly', false);
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

test('shared pages show the section title and highlight the open menu item', function (string $route, string $title, string $menu) {
    $this->post(route('share.verify', $this->link->token), ['pin' => $this->pin]);

    $html = $this->get(route($route, $this->link->token))
        ->assertOk()
        ->assertSee("<h2 class=\"font-semibold text-xl text-gray-800 leading-tight\">{$title}</h2>", false)
        ->getContent();

    // Só o item da tela aberta recebe o sublinhado índigo do x-nav-link.
    preg_match_all('#<a\b[^>]*border-indigo-400[^>]*>\s*([^<]+?)\s*</a>#', $html, $active);

    expect($active[1])->toBe([$menu]);
})->with([
    'dashboard' => ['share.dashboard', 'Dashboard', 'Dashboard'],
    'history' => ['share.history', 'Histórico', 'Histórico'],
    'compulsions' => ['share.compulsions', 'Mapa de compulsões', 'Compulsões'],
    'insights' => ['share.insights', 'Insights', 'Insights'],
]);
