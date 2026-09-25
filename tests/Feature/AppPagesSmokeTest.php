<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);
});

test('main app pages render for an onboarded user', function (string $routeName) {
    $this->actingAs($this->user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'dashboard',
    'patient.edit',
    'emotion-logs.create',
    'emotion-logs.index',
    'reports.index',
    'reminders.index',
    'share.manage',
]);
