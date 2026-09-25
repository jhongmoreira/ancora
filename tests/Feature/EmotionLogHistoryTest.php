<?php

use App\Livewire\EmotionLog\History;
use App\Models\EmotionLog;
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

function createLog(User $user, string $moodKey, \Carbon\Carbon $occurredAt, string $situation = 'Situação de teste'): EmotionLog
{
    $mood = MoodCategory::where('key', $moodKey)->first();
    $feeling = Feeling::where('mood_category_id', $mood->id)->first();

    $log = $user->patient->emotionLogs()->create([
        'mood_category_id' => $mood->id,
        'occurred_at' => $occurredAt,
        'situation' => $situation,
        'action' => 'Ação de teste',
    ]);

    $log->feelings()->sync([$feeling->id]);

    return $log;
}

test('lists only logs within the selected period', function () {
    createLog($this->user, 'positivo', now()->subDays(2), 'Registro recente');
    createLog($this->user, 'positivo', now()->subDays(60), 'Registro antigo');

    Livewire::actingAs($this->user)
        ->test(History::class)
        ->set('period', '30d')
        ->assertSee('Registro recente')
        ->assertDontSee('Registro antigo');

    expect($this->user->patient->emotionLogs()->count())->toBe(2);
});

test('filters by mood category', function () {
    $positive = createLog($this->user, 'positivo', now(), 'Registro positivo');
    createLog($this->user, 'negativo', now(), 'Registro negativo');

    Livewire::actingAs($this->user)
        ->test(History::class)
        ->set('mood', [$positive->mood_category_id])
        ->assertSee('Registro positivo')
        ->assertDontSee('Registro negativo');
});

test('can delete a log after confirming', function () {
    $log = createLog($this->user, 'positivo', now());

    Livewire::actingAs($this->user)
        ->test(History::class)
        ->call('confirmDelete', $log->id)
        ->call('delete', $log->id);

    expect($this->user->patient->emotionLogs()->count())->toBe(0);
});
