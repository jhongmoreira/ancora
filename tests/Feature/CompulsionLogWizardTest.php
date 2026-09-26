<?php

use App\Livewire\Compulsion\LogWizard;
use App\Models\CompulsionLog;
use App\Models\Feeling;
use App\Models\User;
use Database\Seeders\MoodCatalogSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MoodCatalogSeeder::class);

    $this->user = User::factory()->create();
    $this->patient = $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);
    $this->compulsion = $this->patient->compulsions()->create(['name' => 'Pornografia']);

    $this->anxious = Feeling::where('name', 'Ansioso')->first();
    $this->lonely = Feeling::where('name', 'Sozinho')->first();
    $this->guilty = Feeling::where('name', 'Culpado')->first();
    $this->proud = Feeling::where('name', 'Orgulhoso')->first();
});

test('preselects the compulsion when the patient has only one', function () {
    Livewire::actingAs($this->user)
        ->test(LogWizard::class)
        ->assertSet('compulsion_id', $this->compulsion->id);
});

test('records a "gave in" episode with feelings before and after', function () {
    Livewire::actingAs($this->user)
        ->test(LogWizard::class)
        ->set('outcome', CompulsionLog::OUTCOME_GAVE_IN)
        ->set('urge_intensity', 8)
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->set('trigger', 'Sozinho no quarto depois de uma discussão')
        ->call('toggleFeeling', 'before', $this->anxious->id)
        ->call('toggleFeeling', 'before', $this->lonely->id)
        ->set('automatic_thought', 'Só dessa vez')
        ->call('next')
        ->assertSet('step', 3)
        ->call('toggleFeeling', 'after', $this->guilty->id)
        // O mesmo sentimento pode aparecer antes e depois.
        ->call('toggleFeeling', 'after', $this->anxious->id)
        ->set('duration_minutes', 40)
        ->set('coping_strategy', 'Deixar o celular na sala')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('justSaved', true)
        ->assertSet('savedOutcome', CompulsionLog::OUTCOME_GAVE_IN)
        ->assertSee('Registro salvo');

    $log = $this->patient->compulsionLogs()->first();

    expect($log)
        ->compulsion_id->toBe($this->compulsion->id)
        ->outcome->toBe(CompulsionLog::OUTCOME_GAVE_IN)
        ->urge_intensity->toBe(8)
        ->automatic_thought->toBe('Só dessa vez')
        ->duration_minutes->toBe(40)
        ->and($log->feelingsBefore->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->anxious->id, $this->lonely->id])->sort()->values()->all())
        ->and($log->feelingsAfter->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->guilty->id, $this->anxious->id])->sort()->values()->all());
});

test('records a "resisted" episode and ignores duration', function () {
    Livewire::actingAs($this->user)
        ->test(LogWizard::class)
        ->set('outcome', CompulsionLog::OUTCOME_RESISTED)
        ->set('urge_intensity', 6)
        ->call('next')
        ->set('trigger', 'Tédio no fim da tarde')
        ->call('toggleFeeling', 'before', $this->anxious->id)
        ->call('next')
        ->call('toggleFeeling', 'after', $this->proud->id)
        ->set('duration_minutes', 30)
        ->set('coping_strategy', 'Fui caminhar')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Você resistiu!');

    expect($this->patient->compulsionLogs()->first())
        ->outcome->toBe(CompulsionLog::OUTCOME_RESISTED)
        ->duration_minutes->toBeNull()
        ->coping_strategy->toBe('Fui caminhar');
});

test('validates each step before advancing', function () {
    Livewire::actingAs($this->user)
        ->test(LogWizard::class)
        ->call('next')
        ->assertHasErrors(['outcome', 'urge_intensity'])
        ->assertSet('step', 1)
        ->set('outcome', CompulsionLog::OUTCOME_GAVE_IN)
        ->set('urge_intensity', 11)
        ->call('next')
        ->assertHasErrors(['urge_intensity' => 'between'])
        ->set('urge_intensity', 0)
        ->call('next')
        ->assertSet('step', 2)
        ->call('next')
        ->assertHasErrors(['trigger', 'feelings_before'])
        ->assertSet('step', 2);
});

test('rejects dates in the future', function () {
    Livewire::actingAs($this->user)
        ->test(LogWizard::class)
        ->set('occurred_at', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('outcome', CompulsionLog::OUTCOME_GAVE_IN)
        ->set('urge_intensity', 5)
        ->call('next')
        ->assertHasErrors(['occurred_at' => 'before_or_equal']);
});

test('rejects a compulsion from another patient or an archived one', function (string $case) {
    $compulsionId = match ($case) {
        'other patient' => User::factory()->create()->patient()->create([
            'full_name' => 'Outra Pessoa',
            'birth_date' => now()->subYears(30),
        ])->compulsions()->create(['name' => 'Alheia'])->id,
        'archived' => $this->patient->compulsions()->create(['name' => 'Antiga', 'archived_at' => now()])->id,
    };

    Livewire::actingAs($this->user)
        ->test(LogWizard::class)
        ->set('compulsion_id', $compulsionId)
        ->set('outcome', CompulsionLog::OUTCOME_GAVE_IN)
        ->set('urge_intensity', 5)
        ->call('next')
        ->assertHasErrors(['compulsion_id' => 'exists']);
})->with(['other patient', 'archived']);

test('asks to register a compulsion first when there is none', function () {
    $this->compulsion->delete();

    Livewire::actingAs($this->user)
        ->test(LogWizard::class)
        ->assertSee('Cadastrar compulsão');
});
