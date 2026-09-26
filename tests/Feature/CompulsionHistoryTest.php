<?php

use App\Livewire\Compulsion\History;
use App\Models\CompulsionLog;
use App\Models\Feeling;
use App\Models\User;
use App\Services\ShareLinkService;
use Database\Seeders\MoodCatalogSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MoodCatalogSeeder::class);

    // Quarta-feira, 16/09/2026, 15h.
    Carbon::setTestNow(Carbon::parse('2026-09-16 15:00:00'));

    $this->user = User::factory()->create();
    $this->patient = $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);
    $this->porn = $this->patient->compulsions()->create(['name' => 'Pornografia']);
    $this->shopping = $this->patient->compulsions()->create(['name' => 'Compras']);

    $feeling = fn (string $name) => Feeling::where('name', $name)->first()->id;

    $this->addLog = function (array $attributes, array $before = [], array $after = []) use ($feeling) {
        $log = $this->patient->compulsionLogs()->create(array_merge([
            'compulsion_id' => $this->porn->id,
            'urge_intensity' => 5,
            'trigger' => 'Gatilho',
        ], $attributes));

        foreach ($before as $name) {
            $log->feelings()->attach($feeling($name), ['moment' => CompulsionLog::MOMENT_BEFORE]);
        }
        foreach ($after as $name) {
            $log->feelings()->attach($feeling($name), ['moment' => CompulsionLog::MOMENT_AFTER]);
        }

        return $log;
    };

    // Segunda 14/09, 23h — cedeu.
    ($this->addLog)(['occurred_at' => '2026-09-14 23:00', 'outcome' => 'gave_in', 'urge_intensity' => 8, 'trigger' => 'Sozinho à noite'], ['Sozinho', 'Ansioso'], ['Culpado']);
    // Terça 15/09, 22h — resistiu.
    ($this->addLog)(['occurred_at' => '2026-09-15 22:00', 'outcome' => 'resisted', 'urge_intensity' => 6], ['Ansioso'], ['Orgulhoso']);
    // Quarta 16/09, 9h — resistiu (compras).
    ($this->addLog)(['compulsion_id' => $this->shopping->id, 'occurred_at' => '2026-09-16 09:00', 'outcome' => 'resisted', 'urge_intensity' => 4], ['Entediado'], ['Aliviado']);
    // Fora dos últimos 30 dias.
    ($this->addLog)(['occurred_at' => '2026-07-01 10:00', 'outcome' => 'gave_in', 'trigger' => 'Registro antigo'], ['Triste'], ['Culpado']);
});

afterEach(fn () => Carbon::setTestNow());

test('summarizes each compulsion in the period', function () {
    $summary = Livewire::actingAs($this->user)
        ->test(History::class)
        ->instance()
        ->summary
        ->keyBy(fn ($row) => $row['compulsion']->name);

    expect($summary['Pornografia'])
        ->gave_in->toBe(1)
        ->resisted->toBe(1)
        ->avg_urge->toBe(7.0)
        ->days_since_gave_in->toBe(2)
        ->and($summary['Compras'])
        ->gave_in->toBe(0)
        ->resisted->toBe(1)
        ->days_since_gave_in->toBeNull();
});

test('finds the feelings, time of day and weekday patterns', function () {
    $patterns = Livewire::actingAs($this->user)
        ->test(History::class)
        ->instance()
        ->patterns;

    expect($patterns['before']->first())->toBe(['name' => 'Ansioso', 'count' => 2])
        ->and($patterns['after_gave_in']->pluck('name')->all())->toBe(['Culpado'])
        ->and($patterns['after_resisted']->pluck('name')->sort()->values()->all())->toBe(['Aliviado', 'Orgulhoso'])
        ->and($patterns['by_time']['noite'])->toMatchArray(['gave_in' => 1, 'resisted' => 1])
        ->and($patterns['by_time']['manha'])->toMatchArray(['gave_in' => 0, 'resisted' => 1])
        ->and($patterns['by_weekday'][1])->toMatchArray(['gave_in' => 1, 'resisted' => 0])
        ->and($patterns['by_weekday'][3])->toMatchArray(['gave_in' => 0, 'resisted' => 1]);
});

test('filters by period and by compulsion', function () {
    $component = Livewire::actingAs($this->user)
        ->test(History::class)
        ->assertSee('Sozinho à noite')
        ->assertDontSee('Registro antigo');

    $component->set('period', '90d')->assertSee('Registro antigo');

    $component->set('compulsion', $this->shopping->id);

    expect($component->instance()->periodLogs->pluck('compulsion_id')->unique()->all())
        ->toBe([$this->shopping->id]);
});

test('fortnight period covers the last 15 days, for biweekly sessions', function () {
    ($this->addLog)(['occurred_at' => '2026-09-02 10:00', 'outcome' => 'gave_in', 'trigger' => 'Dentro da quinzena']);
    ($this->addLog)(['occurred_at' => '2026-09-01 10:00', 'outcome' => 'gave_in', 'trigger' => 'Fora da quinzena']);

    Livewire::actingAs($this->user)
        ->test(History::class)
        ->set('period', '15d')
        ->assertSet('from', '2026-09-02')
        ->assertSet('to', '2026-09-16')
        ->assertSee('Dentro da quinzena')
        ->assertDontSee('Fora da quinzena');
});

test('patient can delete their own record', function () {
    $log = $this->patient->compulsionLogs()->where('trigger', 'Sozinho à noite')->first();

    Livewire::actingAs($this->user)
        ->test(History::class)
        ->call('confirmDelete', $log->id)
        ->call('delete', $log->id);

    expect($log->fresh())->toBeNull();
});

test('read only view cannot delete records', function () {
    $log = $this->patient->compulsionLogs()->first();

    Livewire::test(History::class, ['patient' => $this->patient, 'readOnly' => true])
        ->assertDontSee('Excluir')
        ->call('delete', $log->id);

    expect($log->fresh())->not->toBeNull();
});

test('psychologist sees the compulsion map through the shared link', function () {
    ['link' => $link, 'pin' => $pin] = app(ShareLinkService::class)->generate($this->patient, now()->addDay());

    $this->get(route('share.compulsions', $link->token))
        ->assertRedirect(route('share.pin', $link->token));

    $this->post(route('share.verify', $link->token), ['pin' => $pin]);

    $this->get(route('share.compulsions', $link->token))
        ->assertOk()
        ->assertSee('Pornografia')
        ->assertSee('Sozinho à noite')
        ->assertDontSee('Fulano de Tal');
});
