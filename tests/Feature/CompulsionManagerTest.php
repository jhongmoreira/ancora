<?php

use App\Livewire\Compulsion\Manager;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->patient = $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);
});

test('patient can add a compulsion', function () {
    Livewire::actingAs($this->user)
        ->test(Manager::class)
        ->set('name', 'Pornografia')
        ->set('description', 'Principalmente à noite')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('name', '');

    expect($this->patient->compulsions()->first())
        ->name->toBe('Pornografia')
        ->description->toBe('Principalmente à noite')
        ->archived_at->toBeNull();
});

test('name is required', function () {
    Livewire::actingAs($this->user)
        ->test(Manager::class)
        ->call('add')
        ->assertHasErrors(['name' => 'required']);
});

test('patient can archive and reactivate a compulsion', function () {
    $compulsion = $this->patient->compulsions()->create(['name' => 'Compras']);

    $component = Livewire::actingAs($this->user)->test(Manager::class);

    $component->call('toggleArchive', $compulsion->id);
    expect($compulsion->fresh()->isArchived())->toBeTrue();

    $component->call('toggleArchive', $compulsion->id);
    expect($compulsion->fresh()->isArchived())->toBeFalse();
});

test('a compulsion with logs cannot be deleted, only archived', function () {
    $compulsion = $this->patient->compulsions()->create(['name' => 'Compras']);
    $this->patient->compulsionLogs()->create([
        'compulsion_id' => $compulsion->id,
        'occurred_at' => now(),
        'outcome' => 'gave_in',
        'urge_intensity' => 7,
        'trigger' => 'Promoção no celular',
    ]);
    $empty = $this->patient->compulsions()->create(['name' => 'Jogos']);

    Livewire::actingAs($this->user)
        ->test(Manager::class)
        ->call('delete', $compulsion->id)
        ->call('delete', $empty->id);

    expect($compulsion->fresh())->not->toBeNull()
        ->and($empty->fresh())->toBeNull();
});

test('patient cannot touch another patient compulsion', function () {
    $other = User::factory()->create();
    $otherCompulsion = $other->patient()->create([
        'full_name' => 'Outra Pessoa',
        'birth_date' => now()->subYears(30),
    ])->compulsions()->create(['name' => 'Alheia']);

    Livewire::actingAs($this->user)
        ->test(Manager::class)
        ->call('toggleArchive', $otherCompulsion->id);
})->throws(ModelNotFoundException::class);
