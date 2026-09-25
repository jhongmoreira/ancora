<?php

use App\Livewire\PatientProfile;
use App\Models\Professional;
use App\Models\User;
use Livewire\Livewire;

test('patient can update their own data', function () {
    $user = User::factory()->create();
    $user->patient()->create();

    Livewire::actingAs($user)
        ->test(PatientProfile::class)
        ->set('full_name', 'Maria Silva')
        ->set('birth_date', '1995-05-20')
        ->set('gender', 'feminino')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->patient->fresh())
        ->full_name->toBe('Maria Silva')
        ->gender->toBe('feminino');
});

test('patient can link an existing professional', function () {
    $user = User::factory()->create();
    $user->patient()->create();
    $professional = Professional::create(['name' => 'Dra. Ana']);

    Livewire::actingAs($user)
        ->test(PatientProfile::class)
        ->set('full_name', 'Maria Silva')
        ->set('birth_date', '1995-05-20')
        ->set('professional_id', $professional->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->patient->fresh()->professional_id)->toBe($professional->id);
});

test('patient can create a new professional inline while saving', function () {
    $user = User::factory()->create();
    $user->patient()->create();

    Livewire::actingAs($user)
        ->test(PatientProfile::class)
        ->set('full_name', 'Maria Silva')
        ->set('birth_date', '1995-05-20')
        ->call('toggleAddProfessional')
        ->set('new_professional_name', 'Dra. Nova')
        ->call('save')
        ->assertHasNoErrors();

    $patient = $user->patient->fresh();

    expect($patient->professional)->not->toBeNull()
        ->and($patient->professional->name)->toBe('Dra. Nova');
});

test('birth_date and full_name are required', function () {
    $user = User::factory()->create();
    $user->patient()->create();

    Livewire::actingAs($user)
        ->test(PatientProfile::class)
        ->set('full_name', '')
        ->set('birth_date', '')
        ->call('save')
        ->assertHasErrors(['full_name', 'birth_date']);
});
