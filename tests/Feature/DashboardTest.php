<?php

use App\Livewire\Dashboard;
use App\Models\Feeling;
use App\Models\MoodCategory;
use App\Models\User;
use Database\Seeders\MoodCatalogSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MoodCatalogSeeder::class);

    $this->user = User::factory()->create();
    $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);
});

test('dashboard renders with no data yet', function () {
    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->assertSee('Nenhum registro no período selecionado');
});

test('chart data aggregates logs by mood category and feeling', function () {
    $positive = MoodCategory::where('key', 'positivo')->first();
    $negative = MoodCategory::where('key', 'negativo')->first();
    $happyFeeling = Feeling::where('mood_category_id', $positive->id)->first();

    $log1 = $this->user->patient->emotionLogs()->create([
        'mood_category_id' => $positive->id,
        'occurred_at' => now(),
        'situation' => 'S1',
        'action' => 'A1',
    ]);
    $log1->feelings()->sync([$happyFeeling->id]);

    $log2 = $this->user->patient->emotionLogs()->create([
        'mood_category_id' => $negative->id,
        'occurred_at' => now(),
        'situation' => 'S2',
        'action' => 'A2',
    ]);

    $component = Livewire::actingAs($this->user)->test(Dashboard::class);
    $data = $component->instance()->chartData();

    expect($data['total'])->toBe(2)
        ->and($data['distribution']->sum())->toBe(2)
        ->and($data['feelingLabels'])->toContain($happyFeeling->name);
});

test('changing the period recalculates the date range', function () {
    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->set('period', '7d')
        ->assertSet('from', now()->subDays(6)->toDateString());
});
