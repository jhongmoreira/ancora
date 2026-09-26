<?php

namespace Database\Seeders;

use App\Models\Compulsion;
use App\Models\CompulsionLog;
use App\Models\Feeling;
use App\Models\Patient;
use App\Models\User;
use App\Services\CompulsionLogService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula ~45 dias de mapa de compulsões para o paciente de DESENVOLVIMENTO
 * LOCAL (docs/14), com padrões que o mapa consegue evidenciar: impulsos à
 * noite e quando está sozinho/ansioso, culpa depois de ceder, orgulho
 * depois de resistir e melhora gradual (mais "resistiu" no fim do período).
 *
 * Só acrescenta: não apaga compulsões nem registros feitos à mão. Para não
 * duplicar, não roda de novo se o paciente já tiver registros de demonstração.
 * Determinístico: usa semente fixa.
 */
class DemoCompulsionLogSeeder extends Seeder
{
    protected const DAYS = 45;

    protected const SEEDED_NOTE = 'Registro de demonstração';

    protected const COMPULSIONS = [
        'Pornografia' => [
            'description' => null,
            // Chance de ter um impulso no dia.
            'daily_chance' => 0.7,
            // Horas prováveis (peso), concentradas na noite/madrugada, com poucas pela manhã.
            'hours' => [0 => 2, 1 => 2, 7 => 1, 9 => 1, 14 => 1, 16 => 1, 22 => 4, 23 => 5],
            'scenarios' => [
                ['trigger' => 'Sozinho no quarto à noite, rolando o celular sem sono', 'thought' => 'Só um pouco pra relaxar', 'before' => ['Sozinho', 'Entediado']],
                ['trigger' => 'Depois de um dia pesado no trabalho, cansado demais pra fazer outra coisa', 'thought' => 'Eu mereço depois desse dia', 'before' => ['Cansado', 'Frustrado']],
                ['trigger' => 'Deitado sem conseguir dormir pensando nas contas', 'thought' => 'Preciso desligar a cabeça de algum jeito', 'before' => ['Ansioso']],
                ['trigger' => 'Fim de semana em casa sem planos, todo mundo saiu', 'thought' => 'Ninguém vai saber mesmo', 'before' => ['Sozinho', 'Triste']],
                ['trigger' => 'Vi uma imagem sugestiva numa rede social', 'thought' => 'Só dessa vez, depois eu paro', 'before' => ['Entediado']],
                ['trigger' => 'Depois de uma discussão com minha namorada por mensagem', 'thought' => 'Não aguento mais esse dia', 'before' => ['Com raiva', 'Triste']],
            ],
            'after_gave_in' => [['Culpado', 'Envergonhado'], ['Culpado', 'Triste'], ['Aliviado', 'Culpado'], ['Envergonhado', 'Sozinho'], ['Frustrado', 'Culpado']],
            'after_resisted' => [['Orgulhoso', 'Aliviado'], ['Orgulhoso', 'Calmo'], ['Confiante'], ['Calmo', 'Cansado'], ['Aliviado', 'Ansioso']],
            'coping_gave_in' => [
                'Ter deixado o celular carregando fora do quarto',
                'Ter levantado e ido para a sala quando percebi a vontade',
                'Ter mandado mensagem para um amigo',
                null,
            ],
            'coping_resisted' => [
                'Deixei o celular na sala e fui ler',
                'Tomei um banho e a vontade passou',
                'Fui caminhar no quarteirão por 15 minutos',
                'Liguei para meu irmão e conversamos um pouco',
                'Fiz o exercício de respiração e esperei a onda passar',
            ],
            'duration' => [15, 90],
        ],
    ];

    public function run(): void
    {
        $user = User::where('email', 'dev@ancora.test')->first();
        $patient = $user?->patient;

        if (! $patient) {
            $this->command?->warn('Paciente dev@ancora.test não encontrado — rode o DemoUserSeeder antes.');

            return;
        }

        if ($patient->compulsionLogs()->where('notes', self::SEEDED_NOTE)->exists()) {
            $this->command?->warn('Os registros de demonstração de compulsões já existem — nada a fazer.');

            return;
        }

        mt_srand(20260927);

        DB::transaction(function () use ($patient) {
            foreach (self::COMPULSIONS as $name => $config) {
                $compulsion = $patient->compulsions()->firstOrCreate(['name' => $name], ['description' => $config['description']]);

                $this->seedLogs($patient, $compulsion, $config);
            }
        });
    }

    protected function seedLogs(Patient $patient, Compulsion $compulsion, array $config): void
    {
        $service = app(CompulsionLogService::class);
        $feelingIds = Feeling::pluck('id', 'name');
        $ids = fn (array $names) => collect($names)->map(fn ($n) => $feelingIds[$n])->all();

        for ($daysAgo = self::DAYS; $daysAgo >= 0; $daysAgo--) {
            $day = now()->subDays($daysAgo)->startOfDay();
            // Fins de semana têm mais impulsos.
            $chance = $config['daily_chance'] * ($day->isWeekend() ? 1.3 : 1);

            if (mt_rand() / mt_getrandmax() > $chance) {
                continue;
            }

            $at = $day->copy()->setTime((int) $this->weightedPick($config['hours']), mt_rand(0, 59));
            if ($at->isFuture()) {
                continue;
            }

            // 0 no início do período, 1 hoje: a chance de resistir sobe de ~25% para ~70%.
            $progress = 1 - $daysAgo / self::DAYS;
            $resisted = mt_rand() / mt_getrandmax() < 0.25 + 0.45 * $progress;
            $scenario = $config['scenarios'][array_rand($config['scenarios'])];

            $service->create($patient, [
                'compulsion_id' => $compulsion->id,
                'occurred_at' => $at,
                'outcome' => $resisted ? CompulsionLog::OUTCOME_RESISTED : CompulsionLog::OUTCOME_GAVE_IN,
                // Quem cede costuma relatar vontade mais forte.
                'urge_intensity' => $resisted ? mt_rand(3, 8) : mt_rand(6, 10),
                'trigger' => $scenario['trigger'],
                'automatic_thought' => mt_rand(1, 4) === 1 ? null : $scenario['thought'],
                'duration_minutes' => $resisted ? null : mt_rand(...$config['duration']),
                'coping_strategy' => $this->pick($config[$resisted ? 'coping_resisted' : 'coping_gave_in']),
                'notes' => self::SEEDED_NOTE,
                'feelings_before' => $ids($scenario['before']),
                'feelings_after' => $ids($this->pick($config[$resisted ? 'after_resisted' : 'after_gave_in'])),
            ]);
        }
    }

    protected function pick(array $options): mixed
    {
        return $options[array_rand($options)];
    }

    protected function weightedPick(array $weights): int|string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $value;
            }
        }

        return array_key_last($weights);
    }
}
