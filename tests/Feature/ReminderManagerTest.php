<?php

use App\Livewire\ReminderManager;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);
});

test('can add a reminder', function () {
    Livewire::actingAs($this->user)
        ->test(ReminderManager::class)
        ->set('time', '08:00')
        ->set('label', 'Lembrete da manhã')
        ->call('add')
        ->assertHasNoErrors();

    expect($this->user->patient->reminders()->count())->toBe(1);
});

test('cannot have more than 3 active reminders', function () {
    $component = Livewire::actingAs($this->user)->test(ReminderManager::class);

    foreach (['08:00', '12:00', '18:00'] as $time) {
        $component->set('time', $time)->call('add');
    }

    $component->set('time', '20:00')->call('add')->assertHasErrors('time');

    expect($this->user->patient->reminders()->count())->toBe(3);
});

test('can toggle a reminder active state', function () {
    $reminder = $this->user->patient->reminders()->create(['time' => '08:00', 'is_active' => true]);

    Livewire::actingAs($this->user)
        ->test(ReminderManager::class)
        ->call('toggle', $reminder->id);

    expect($reminder->fresh()->is_active)->toBeFalse();
});

test('cannot activate a 4th reminder', function () {
    $this->user->patient->reminders()->create(['time' => '08:00', 'is_active' => true]);
    $this->user->patient->reminders()->create(['time' => '12:00', 'is_active' => true]);
    $this->user->patient->reminders()->create(['time' => '18:00', 'is_active' => true]);
    $inactive = $this->user->patient->reminders()->create(['time' => '20:00', 'is_active' => false]);

    Livewire::actingAs($this->user)
        ->test(ReminderManager::class)
        ->call('toggle', $inactive->id)
        ->assertHasErrors('time');

    expect($inactive->fresh()->is_active)->toBeFalse();
});

test('can delete a reminder', function () {
    $reminder = $this->user->patient->reminders()->create(['time' => '08:00', 'is_active' => true]);

    Livewire::actingAs($this->user)
        ->test(ReminderManager::class)
        ->call('delete', $reminder->id);

    expect($this->user->patient->reminders()->count())->toBe(0);
});
