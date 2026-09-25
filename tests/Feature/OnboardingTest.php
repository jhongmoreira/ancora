<?php

use App\Models\User;

test('guest is redirected to login when visiting the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('user with incomplete patient profile is redirected to patient.edit', function () {
    $user = User::factory()->create();
    $user->patient()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('patient.edit'));
});

test('user with complete patient profile can access the dashboard', function () {
    $user = User::factory()->create();
    $user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
