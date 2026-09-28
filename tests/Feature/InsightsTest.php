<?php

use App\Livewire\Insights\Consent;
use App\Livewire\Insights\Report;
use App\Models\CompulsionLog;
use App\Models\Feeling;
use App\Models\InsightReport;
use App\Models\MoodCategory;
use App\Models\Professional;
use App\Models\User;
use App\Services\Insights\InsightAnonymizer;
use App\Services\Insights\InsightDataBuilder;
use App\Services\Insights\InsightReportService;
use App\Services\Insights\InsightUnavailableException;
use App\Services\Insights\RiskDetector;
use App\Services\ShareLinkService;
use Database\Seeders\MoodCatalogSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MoodCatalogSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-09-26 15:00:00'));
    Http::preventStrayRequests();
    Sleep::fake();

    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-principal',
        'services.gemini.fallback_model' => 'gemini-reserva',
    ]);

    $professional = Professional::create(['name' => 'Dra. Helena Martins']);
    $this->user = User::factory()->create();
    $this->patient = $this->user->patient()->create([
        'full_name' => 'Fulano Souza de Tal',
        'birth_date' => now()->subYears(25),
        'professional_id' => $professional->id,
        'ai_consent_at' => now()->subDay(),
    ]);

    $negative = MoodCategory::where('key', 'negativo')->first();
    $this->addEmotion = fn (string $at, string $situation, ?string $thought = null) => $this->patient->emotionLogs()->create([
        'mood_category_id' => $negative->id,
        'occurred_at' => $at,
        'intensity' => 4,
        'situation' => $situation,
        'action' => 'Fiquei no quarto',
        'automatic_thought' => $thought,
    ]);

    $this->e1 = ($this->addEmotion)('2026-09-12 09:00', 'Primeiro dia da quinzena', 'Nunca vou dar conta');
    $this->e2 = ($this->addEmotion)('2026-09-20 10:00', 'Discussão com o Carlos no trabalho, liguei pro Fulano no 11 98765-4321');
    $this->e3 = ($this->addEmotion)('2026-09-26 08:00', 'Reunião tensa com a Dra. Helena Martins');
    // Dia 16 para trás: fora da quinzena.
    ($this->addEmotion)('2026-09-11 23:59', 'Registro de fora da quinzena');

    $compulsion = $this->patient->compulsions()->create(['name' => 'Pornografia']);
    $this->c1 = $this->patient->compulsionLogs()->create([
        'compulsion_id' => $compulsion->id,
        'occurred_at' => '2026-09-25 23:00',
        'outcome' => CompulsionLog::OUTCOME_RESISTED,
        'urge_intensity' => 8,
        'trigger' => 'Sozinho à noite',
        'automatic_thought' => 'Só dessa vez',
        'coping_strategy' => 'Fui caminhar',
    ]);
    $this->c1->feelings()->attach(Feeling::where('name', 'Sozinho')->first()->id, ['moment' => CompulsionLog::MOMENT_BEFORE]);

    $this->content = fn (array $overrides = []) => array_merge([
        'visao_geral' => 'Quinzena com momentos de ansiedade ligados ao trabalho.',
        'padroes' => [['titulo' => 'Trabalho e ansiedade', 'descricao' => 'Pode indicar...', 'evidencias' => ["E{$this->e1->id}", "E{$this->e2->id}"]]],
        'ciclo' => ['situacao' => 'Cobrança', 'pensamento' => 'Não dou conta', 'emocao' => 'Ansiedade', 'comportamento' => 'Isolamento'],
        'pensamentos' => [['citacao' => 'Nunca vou dar conta', 'registro' => "E{$this->e1->id}", 'possivel_distorcao' => 'catastrofizacao', 'explicacao' => 'Antecipa o pior.']],
        'gatilhos' => [['descricao' => 'Reuniões', 'emocoes' => ['Ansioso']]],
        'compulsoes' => ['ciclo' => 'À noite', 'alto_risco' => 'Sozinho', 'o_que_ajudou' => 'Caminhar'],
        'estrategias' => ['funcionaram' => ['Caminhar'], 'pouco_efetivas' => []],
        'reconhecimentos' => ['Resistiu ao impulso'],
        'perguntas_sessao' => ['O que torna as reuniões difíceis?'],
        'limitacoes' => ['Poucos registros'],
    ], $overrides);

    $this->geminiResponse = fn (array $content) => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode($content)]]], 'finishReason' => 'STOP']],
        'usageMetadata' => ['totalTokenCount' => 1234],
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    RateLimiter::clear("insights:cooldown:{$this->patient->id}");
    RateLimiter::clear("insights:daily:{$this->patient->id}");
});

test('the data package covers exactly the last 15 days of this patient', function () {
    $other = User::factory()->create()->patient()->create(['full_name' => 'Outra Pessoa', 'birth_date' => now()->subYears(30)]);
    $other->emotionLogs()->create([
        'mood_category_id' => MoodCategory::first()->id,
        'occurred_at' => '2026-09-20 10:00',
        'situation' => 'Registro de outra pessoa',
        'action' => 'Nada',
    ]);

    ['period_start' => $from, 'period_end' => $to] = InsightDataBuilder::resolvePeriod('15d');
    $data = app(InsightDataBuilder::class)->build($this->patient, $from, $to);

    expect($data['period_start'])->toBe('2026-09-12')
        ->and($data['period_end'])->toBe('2026-09-26')
        ->and($data['record_ids'])->toEqualCanonicalizing(["E{$this->e1->id}", "E{$this->e2->id}", "E{$this->e3->id}", "C{$this->c1->id}"])
        ->and($data['stats']['emotion']['total'])->toBe(3)
        ->and($data['payload']['registros'][0]['dia'])->toBe(1);
});

test('period presets resolve to the same ranges as the other screens', function (string $preset, string $start) {
    expect(InsightDataBuilder::resolvePeriod($preset))->toBe(['period_start' => $start, 'period_end' => '2026-09-26']);
})->with([
    '7 dias' => ['7d', '2026-09-20'],
    'quinzena' => ['15d', '2026-09-12'],
    '30 dias' => ['30d', '2026-08-28'],
    '90 dias' => ['90d', '2026-06-29'],
    'este mês' => ['month', '2026-09-01'],
]);

test('custom period is validated', function (?string $from, ?string $to, string $message) {
    expect(fn () => InsightDataBuilder::resolvePeriod('custom', $from, $to))
        ->toThrow(InsightUnavailableException::class, $message);
})->with([
    'sem datas' => [null, null, 'Informe as datas'],
    'invertido' => ['2026-09-20', '2026-09-10', 'anterior'],
    'futuro' => ['2026-09-20', '2026-09-27', 'futuro'],
    'longo demais' => ['2026-01-01', '2026-09-26', 'até 90 dias'],
]);

test('saves the chosen period with the report', function () {
    Http::fake(['*' => ($this->geminiResponse)(($this->content)())]);

    $report = app(InsightReportService::class)->generate($this->patient, 'custom', '2026-09-11', '2026-09-20');

    expect($report->period_preset)->toBe('custom')
        ->and($report->period_start->toDateString())->toBe('2026-09-11')
        ->and($report->period_end->toDateString())->toBe('2026-09-20')
        ->and($report->periodLabel())->toBe('Personalizado')
        // Inclui o registro do dia 11 e exclui o do dia 26.
        ->and(collect($report->payload['registros'])->pluck('situacao'))->toContain('Registro de fora da quinzena')
        ->and(collect($report->payload['registros'])->pluck('id'))->not->toContain("E{$this->e3->id}")
        ->and($report->payload['periodo']['dias'])->toBe(10);
});

test('patient picks the period on the page, and editing a date switches to custom', function () {
    Http::fake(['*' => ($this->geminiResponse)(($this->content)())]);

    $component = Livewire::actingAs($this->user)
        ->test(Report::class)
        ->assertSet('period', '15d')
        ->assertSet('from', '2026-09-12')
        ->set('period', '7d')
        ->assertSet('from', '2026-09-20')
        ->set('from', '2026-09-11')
        ->assertSet('period', 'custom')
        ->call('generate')
        ->assertSee('Personalizado (11/09 a 26/09)');

    expect($this->patient->insightReports()->first()->period_preset)->toBe('custom');

    $component->set('to', '2026-09-30')->call('generate')->assertSee('O período não pode terminar no futuro.');
});

test('names, phones and professional are removed before sending to the AI', function () {
    Http::fake(['*' => ($this->geminiResponse)(($this->content)())]);

    app(InsightReportService::class)->generate($this->patient);

    Http::assertSent(function (Request $request) {
        $body = $request->body();

        return $request->hasHeader('x-goog-api-key', 'test-key')
            && ! str_contains($body, 'Fulano')
            && ! str_contains($body, 'Souza')
            && ! str_contains($body, 'Helena')
            && ! str_contains($body, 'Martins')
            && ! str_contains($body, 'Carlos')
            && ! str_contains($body, '98765')
            && ! str_contains($body, '2026-09')
            && str_contains($body, 'Nunca vou dar conta');
    });
});

test('anonymizer keeps sentence starts and allowed words, removes probable names', function () {
    $anonymizer = new InsightAnonymizer(['Maria Clara Souza', 'Dra. Helena Martins']);

    expect($anonymizer->anonymize('Reunião na Sexta com o Pedro e a maria'))->toBe('Reunião na Sexta com o [nome] e a [nome]')
        ->and($anonymizer->anonymize('Mandei para ana@mail.com e www.site.com'))->toBe('Mandei para [email] e [link]')
        ->and($anonymizer->anonymize('A Dra. Helena disse'))->toBe('A Dra. [nome] disse');
});

test('generates and saves a completed report with validated content', function () {
    Http::fake(['*' => ($this->geminiResponse)(($this->content)([
        'padroes' => [['titulo' => 'Padrão', 'descricao' => 'Descrição', 'evidencias' => ["E{$this->e1->id}", 'E999999']]],
        'pensamentos' => [
            ['citacao' => 'Nunca vou dar conta', 'registro' => "E{$this->e1->id}", 'possivel_distorcao' => 'catastrofizacao', 'explicacao' => 'x'],
            ['citacao' => 'Inventado', 'registro' => 'E999999', 'possivel_distorcao' => 'catastrofizacao', 'explicacao' => 'x'],
            ['citacao' => 'Outra', 'registro' => "E{$this->e1->id}", 'possivel_distorcao' => 'inexistente', 'explicacao' => 'x'],
        ],
    ]))]);

    $report = app(InsightReportService::class)->generate($this->patient);

    expect($report->status)->toBe(InsightReport::STATUS_COMPLETED)
        ->and($report->model)->toBe('gemini-principal')
        ->and($report->content['padroes'][0]['evidencias'])->toBe(["E{$this->e1->id}"])
        ->and($report->content['pensamentos'])->toHaveCount(1)
        ->and($report->usage['totalTokenCount'])->toBe(1234)
        ->and($report->payload['registros'])->toHaveCount(4)
        ->and($report->risk_flag)->toBeFalse();
});

test('falls back to the second model when the first is rate limited', function () {
    Http::fake([
        '*gemini-principal*' => Http::response(['error' => ['message' => 'quota']], 429),
        '*gemini-reserva*' => ($this->geminiResponse)(($this->content)()),
    ]);

    $report = app(InsightReportService::class)->generate($this->patient);

    expect($report->status)->toBe(InsightReport::STATUS_COMPLETED)
        ->and($report->model)->toBe('gemini-reserva');
});

test('saves a failed report with a friendly error when the AI fails', function (Closure $response, string $message) {
    Http::fake(['*' => $response()]);

    $report = app(InsightReportService::class)->generate($this->patient);

    expect($report->status)->toBe(InsightReport::STATUS_FAILED)
        ->and($report->content)->toBeNull()
        ->and($report->error)->toContain($message);
})->with([
    'quota exhausted' => [fn () => Http::response([], 429), 'limite gratuito'],
    'invalid json' => [fn () => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'não é json']]], 'finishReason' => 'STOP']]]), 'formato inesperado'],
    'safety block' => [fn () => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]), 'recusou'],
    'missing summary' => [fn () => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"padroes":[]}']]], 'finishReason' => 'STOP']]]), 'incompleta'],
]);

test('does not generate without consent, with too few records or too often', function () {
    Http::fake(['*' => ($this->geminiResponse)(($this->content)())]);
    $service = app(InsightReportService::class);

    $this->patient->update(['ai_consent_at' => null]);
    expect(fn () => $service->generate($this->patient))->toThrow(InsightUnavailableException::class, 'aceitar');

    $this->patient->update(['ai_consent_at' => now()]);
    $service->generate($this->patient);
    expect(fn () => $service->generate($this->patient))->toThrow(InsightUnavailableException::class, 'Aguarde');

    $this->patient->emotionLogs()->delete();
    $this->patient->compulsionLogs()->delete();
    RateLimiter::clear("insights:cooldown:{$this->patient->id}");
    expect(fn () => $service->generate($this->patient))->toThrow(InsightUnavailableException::class, 'pelo menos 3');

    Http::assertSentCount(1);
});

test('the daily and cooldown limits are read from config, not hardcoded', function () {
    config(['insights.max_reports_per_day' => 1, 'insights.cooldown_seconds' => 0]);
    Http::fake(['*' => ($this->geminiResponse)(($this->content)())]);
    $service = app(InsightReportService::class);

    $service->generate($this->patient);

    expect(fn () => $service->generate($this->patient))
        ->toThrow(InsightUnavailableException::class, 'limite de 1 relatórios por dia');

    Http::assertSentCount(1);
});

test('risk detector flags self-harm language, ignores common idioms', function () {
    $detector = new RiskDetector;

    expect($detector->isRisky('Pensei em me matar'))->toBeTrue()
        ->and($detector->isRisky('Não quero mais viver assim'))->toBeTrue()
        ->and($detector->isRisky('queria sumir de vez'))->toBeTrue()
        ->and($detector->isRisky('Me cortei ontem'))->toBeTrue()
        ->and($detector->isRisky('Morri de rir no almoço'))->toBeFalse()
        ->and($detector->isRisky('Estou morrendo de vergonha'))->toBeFalse()
        ->and($detector->isRisky('Cortei o cabelo'))->toBeFalse();
});

test('risky records flag the report regardless of the AI', function () {
    ($this->addEmotion)('2026-09-24 22:00', 'Dia horrível', 'Às vezes penso em tirar a minha vida');
    Http::fake(['*' => ($this->geminiResponse)(($this->content)())]);

    $report = app(InsightReportService::class)->generate($this->patient);

    expect($report->risk_flag)->toBeTrue()
        ->and($report->risk_record_ids)->toHaveCount(1);

    Livewire::actingAs($this->user)->test(Report::class)->assertSee('CVV — 188');
});

test('patient generates from the page after accepting the consent', function () {
    $this->patient->update(['ai_consent_at' => null]);
    Http::fake(['*' => ($this->geminiResponse)(($this->content)())]);

    Livewire::actingAs($this->user)
        ->test(Report::class)
        ->assertSee('Antes de usar a IA')
        ->call('acceptConsent')
        ->assertSee('Quinzena com momentos de ansiedade ligados ao trabalho.')
        ->assertSee('Catastrofização')
        ->assertSee('Nunca vou dar conta');

    expect($this->patient->fresh()->hasAiConsent())->toBeTrue()
        ->and($this->patient->insightReports()->count())->toBe(1);
});

test('patient sees the error of a failed attempt', function () {
    Http::fake(['*' => Http::response([], 503)]);

    Livewire::actingAs($this->user)
        ->test(Report::class)
        ->call('generate')
        ->assertSee('Não foi possível gerar o último relatório');
});

test('psychologist sees the report through the shared link but cannot generate', function () {
    Http::fake(['*' => ($this->geminiResponse)(($this->content)())]);
    $report = app(InsightReportService::class)->generate($this->patient);
    ['link' => $link, 'pin' => $pin] = app(ShareLinkService::class)->generate($this->patient, now()->addDay());

    $this->get(route('share.insights', $link->token))->assertRedirect(route('share.pin', $link->token));
    $this->post(route('share.verify', $link->token), ['pin' => $pin]);

    $this->get(route('share.insights', $link->token))
        ->assertOk()
        ->assertSee('Quinzena com momentos de ansiedade ligados ao trabalho.')
        ->assertDontSee('Gerar relatório')
        ->assertDontSee('Ver o que foi enviado');

    Livewire::test(Report::class, ['patient' => $this->patient, 'readOnly' => true, 'shareToken' => $link->token])
        ->call('generate')
        ->call('acceptConsent');

    expect($this->patient->insightReports()->count())->toBe(1);
    Http::assertSentCount(1);
});

test('open shared insights stop returning data after the link is revoked', function () {
    Http::fake(['*' => ($this->geminiResponse)(($this->content)())]);
    $report = app(InsightReportService::class)->generate($this->patient);
    ['link' => $link, 'pin' => $pin] = app(ShareLinkService::class)->generate($this->patient, now()->addDay());
    $this->post(route('share.verify', $link->token), ['pin' => $pin]);

    $view = Livewire::test(Report::class, ['patient' => $this->patient, 'readOnly' => true, 'shareToken' => $link->token])
        ->assertSee('Quinzena com momentos de ansiedade');

    app(ShareLinkService::class)->revoke($this->patient);

    $view->call('select', $report->id)
        ->assertRedirect(route('share.pin', $link->token));

    expect($view->effects)->not->toHaveKey('html');
});

test('psychologist does not see failed attempts', function () {
    $this->patient->insightReports()->create([
        'period_preset' => '15d', 'period_start' => '2026-09-12', 'period_end' => '2026-09-26', 'status' => InsightReport::STATUS_FAILED,
        'prompt_version' => '1', 'stats' => [], 'payload' => [], 'error' => 'Falha técnica',
    ]);

    Livewire::test(Report::class, ['patient' => $this->patient, 'readOnly' => true])
        ->assertSee('O paciente ainda não gerou insights')
        ->assertDontSee('Falha técnica');
});

test('patient can switch between saved reports', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode(($this->content)(['visao_geral' => 'Primeiro relatório']))]]], 'finishReason' => 'STOP']]])
        ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode(($this->content)(['visao_geral' => 'Segundo relatório']))]]], 'finishReason' => 'STOP']]]),
    ]);

    $service = app(InsightReportService::class);
    $first = $service->generate($this->patient);
    RateLimiter::clear("insights:cooldown:{$this->patient->id}");
    $service->generate($this->patient);

    Livewire::actingAs($this->user)
        ->test(Report::class)
        ->assertSee('Segundo relatório')
        ->call('select', $first->id)
        ->assertSee('Primeiro relatório');
});

test('consent can be revoked in Meus dados', function () {
    Livewire::actingAs($this->user)
        ->test(Consent::class)
        ->assertSee('Retirar autorização')
        ->call('revoke');

    expect($this->patient->fresh()->hasAiConsent())->toBeFalse();
});
