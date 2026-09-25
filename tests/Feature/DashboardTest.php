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

test('quinzena period covers the last 15 days', function () {
    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->set('period', '15d')
        ->assertSet('from', now()->subDays(14)->toDateString())
        ->assertSet('to', now()->toDateString());
});

test('consistency counts distinct days with at least one log', function () {
    $mood = MoodCategory::first();

    foreach ([now(), now(), now()->subDay(), now()->subDays(10)] as $when) {
        $this->user->patient->emotionLogs()->create([
            'mood_category_id' => $mood->id,
            'occurred_at' => $when,
            'situation' => 'S',
            'action' => 'A',
        ]);
    }

    $component = Livewire::actingAs($this->user)->test(Dashboard::class)->set('period', '30d');
    $consistency = $component->instance()->consistencyData();

    expect($consistency['daysWithLogs'])->toBe(3)
        ->and($consistency['totalDays'])->toBe(30);
});

test('streak counts consecutive days ending today', function () {
    $mood = MoodCategory::first();

    foreach ([now(), now()->subDay(), now()->subDays(2), now()->subDays(5)] as $when) {
        $this->user->patient->emotionLogs()->create([
            'mood_category_id' => $mood->id,
            'occurred_at' => $when,
            'situation' => 'S',
            'action' => 'A',
        ]);
    }

    $component = Livewire::actingAs($this->user)->test(Dashboard::class);

    expect($component->instance()->streakData()['current'])->toBe(3);
});

test('streak keeps counting yesterday even if today has no log yet', function () {
    $mood = MoodCategory::first();

    foreach ([now()->subDay(), now()->subDays(2)] as $when) {
        $this->user->patient->emotionLogs()->create([
            'mood_category_id' => $mood->id,
            'occurred_at' => $when,
            'situation' => 'S',
            'action' => 'A',
        ]);
    }

    $component = Livewire::actingAs($this->user)->test(Dashboard::class);

    expect($component->instance()->streakData()['current'])->toBe(2);
});

test('streak is zero when there is a gap before today and yesterday', function () {
    $mood = MoodCategory::first();

    $this->user->patient->emotionLogs()->create([
        'mood_category_id' => $mood->id,
        'occurred_at' => now()->subDays(3),
        'situation' => 'S',
        'action' => 'A',
    ]);

    $component = Livewire::actingAs($this->user)->test(Dashboard::class);

    expect($component->instance()->streakData()['current'])->toBe(0);
});

test('heatmap only counts negative mood logs bucketed by weekday and period of day', function () {
    $negative = MoodCategory::where('key', 'negativo')->first();
    $positive = MoodCategory::where('key', 'positivo')->first();

    $monday8am = Carbon\Carbon::parse('last monday')->setTime(8, 0);

    $this->user->patient->emotionLogs()->create([
        'mood_category_id' => $negative->id,
        'occurred_at' => $monday8am,
        'situation' => 'S',
        'action' => 'A',
    ]);

    $this->user->patient->emotionLogs()->create([
        'mood_category_id' => $positive->id,
        'occurred_at' => $monday8am,
        'situation' => 'S',
        'action' => 'A',
    ]);

    $component = Livewire::actingAs($this->user)->test(Dashboard::class)->set('period', '30d');
    $heatmap = $component->instance()->heatmapData();

    expect($heatmap['total'])->toBe(1)
        ->and($heatmap['grid']['Seg']['Manhã'])->toBe(1)
        ->and($heatmap['grid']['Seg']['Tarde'])->toBe(0);
});
