<?php

use App\Models\MoodCategory;
use App\Models\Professional;
use App\Models\User;
use Database\Seeders\MoodCatalogSeeder;

beforeEach(function () {
    $this->seed(MoodCatalogSeeder::class);

    $professional = Professional::create(['name' => 'Dra. Ana']);

    $this->user = User::factory()->create();
    $this->user->patient()->create([
        'full_name' => 'Fulano de Tal',
        'birth_date' => now()->subYears(25),
        'professional_id' => $professional->id,
    ]);

    $mood = MoodCategory::first();

    $this->user->patient->emotionLogs()->create([
        'mood_category_id' => $mood->id,
        'occurred_at' => now(),
        'situation' => 'Situação exportável',
        'action' => 'Ação exportável',
    ]);
});

test('reports index shows the count for the selected period', function () {
    $this->actingAs($this->user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('1');
});

test('pdf export downloads a pdf file with the correct content type', function () {
    $response = $this->actingAs($this->user)->get(route('reports.pdf', [
        'from' => now()->subDays(7)->toDateString(),
        'to' => now()->toDateString(),
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('excel export downloads a spreadsheet file', function () {
    $response = $this->actingAs($this->user)->get(route('reports.excel', [
        'from' => now()->subDays(7)->toDateString(),
        'to' => now()->toDateString(),
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheet');
});

test('export requires from and to dates', function () {
    $this->actingAs($this->user)
        ->get(route('reports.pdf'))
        ->assertSessionHasErrors(['from', 'to']);
});

test('export only includes the authenticated patient logs within the period', function () {
    $otherUser = User::factory()->create();
    $otherUser->patient()->create(['full_name' => 'Outro', 'birth_date' => now()->subYears(30)]);
    $otherUser->patient->emotionLogs()->create([
        'mood_category_id' => MoodCategory::first()->id,
        'occurred_at' => now(),
        'situation' => 'Não deveria aparecer',
        'action' => 'Ação',
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.pdf', [
        'from' => now()->subDays(7)->toDateString(),
        'to' => now()->toDateString(),
    ]));

    $response->assertOk();
    expect($response->getContent())->not->toContain('Não deveria aparecer');
});
