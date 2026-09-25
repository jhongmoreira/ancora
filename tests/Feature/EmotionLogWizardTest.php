<?php

use App\Livewire\EmotionLog\CreateWizard;
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

test('completes the minimal 4-step flow without automatic thought', function () {
    $mood = MoodCategory::where('key', 'positivo')->first();
    $feeling = Feeling::where('mood_category_id', $mood->id)->first();

    Livewire::actingAs($this->user)
        ->test(CreateWizard::class)
        ->call('selectMood', $mood->id)
        ->call('next')
        ->assertSet('step', 2)
        ->call('toggleFeeling', $feeling->id)
        ->call('next')
        ->assertSet('step', 3)
        ->set('situation', 'Reunião de trabalho tensa')
        ->call('next')
        ->assertSet('step', 4)
        ->set('action', 'Respirei fundo e pedi uma pausa')
        ->call('save')
        ->assertSet('justSaved', true);

    expect($this->user->patient->emotionLogs()->count())->toBe(1);

    $log = $this->user->patient->emotionLogs()->first();

    expect($log->mood_category_id)->toBe($mood->id)
        ->and($log->automatic_thought)->toBeNull()
        ->and($log->feelings->pluck('id'))->toContain($feeling->id);
});

test('completes the full 5-step flow with intensity and automatic thought', function () {
    $mood = MoodCategory::where('key', 'negativo')->first();
    $feeling = Feeling::where('mood_category_id', $mood->id)->first();

    Livewire::actingAs($this->user)
        ->test(CreateWizard::class)
        ->call('selectMood', $mood->id)
        ->set('intensity', 4)
        ->call('next')
        ->call('toggleFeeling', $feeling->id)
        ->call('next')
        ->set('situation', 'Prova importante amanhã')
        ->call('next')
        ->set('action', 'Fiquei estudando até tarde')
        ->call('next')
        ->assertSet('step', 5)
        ->set('automatic_thought', 'Vou ser reprovado')
        ->call('save')
        ->assertSet('justSaved', true);

    $log = $this->user->patient->emotionLogs()->first();

    expect($log->intensity)->toBe(4)
        ->and($log->automatic_thought)->toBe('Vou ser reprovado');
});

test('cannot advance past step 1 without selecting a mood', function () {
    Livewire::actingAs($this->user)
        ->test(CreateWizard::class)
        ->call('next')
        ->assertHasErrors('mood_category_id')
        ->assertSet('step', 1);
});

test('cannot advance past step 2 without selecting at least one feeling', function () {
    $mood = MoodCategory::first();

    Livewire::actingAs($this->user)
        ->test(CreateWizard::class)
        ->call('selectMood', $mood->id)
        ->call('next')
        ->call('next')
        ->assertHasErrors('feeling_ids')
        ->assertSet('step', 2);
});

test('skip and save clears the automatic thought', function () {
    $mood = MoodCategory::first();
    $feeling = Feeling::where('mood_category_id', $mood->id)->first();

    Livewire::actingAs($this->user)
        ->test(CreateWizard::class)
        ->call('selectMood', $mood->id)
        ->call('next')
        ->call('toggleFeeling', $feeling->id)
        ->call('next')
        ->set('situation', 'Situação qualquer')
        ->call('next')
        ->set('action', 'Ação qualquer')
        ->call('next')
        ->set('automatic_thought', 'Um pensamento')
        ->call('skipThought');

    expect($this->user->patient->emotionLogs()->first()->automatic_thought)->toBeNull();
});
