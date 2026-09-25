<?php

use App\Models\ReminderDispatchLog;
use App\Models\User;
use App\Notifications\EmotionLogReminderNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
    ]);
});

test('sends a notification for a reminder due right now', function () {
    Notification::fake();

    $reminder = $this->user->patient->reminders()->create([
        'time' => now()->format('H:i:s'),
        'is_active' => true,
    ]);

    $this->artisan('reminders:dispatch')->assertSuccessful();

    Notification::assertSentTo($this->user, EmotionLogReminderNotification::class);
    expect(ReminderDispatchLog::where('reminder_id', $reminder->id)->count())->toBe(1);
});

test('does not send a notification twice for the same day', function () {
    Notification::fake();

    $this->user->patient->reminders()->create([
        'time' => now()->format('H:i:s'),
        'is_active' => true,
    ]);

    $this->artisan('reminders:dispatch');
    $this->artisan('reminders:dispatch');

    Notification::assertSentToTimes($this->user, EmotionLogReminderNotification::class, 1);
});

test('does not send a notification for an inactive reminder', function () {
    Notification::fake();

    $this->user->patient->reminders()->create([
        'time' => now()->format('H:i:s'),
        'is_active' => false,
    ]);

    $this->artisan('reminders:dispatch');

    Notification::assertNotSentTo($this->user, EmotionLogReminderNotification::class);
});

test('does not send a notification for a reminder outside the tolerance window', function () {
    Notification::fake();

    $this->user->patient->reminders()->create([
        'time' => now()->subHours(2)->format('H:i:s'),
        'is_active' => true,
    ]);

    $this->artisan('reminders:dispatch');

    Notification::assertNotSentTo($this->user, EmotionLogReminderNotification::class);
});
