<?php

namespace App\Services\Insights;

/**
 * Formato da resposta pedida ao Gemini (responseSchema) e a lista fechada
 * de distorções cognitivas aceitas — docs/15, seção 5.2.
 */
class InsightSchema
{
    public const DISTORTIONS = [
        'catastrofizacao' => 'Catastrofização',
        'leitura_mental' => 'Leitura mental',
        'previsao_do_futuro' => 'Previsão do futuro',
        'tudo_ou_nada' => 'Pensamento tudo-ou-nada',
        'supergeneralizacao' => 'Supergeneralização',
        'rotulacao' => 'Rotulação',
        'deveria' => 'Afirmações do tipo "deveria"',
        'personalizacao' => 'Personalização',
        'filtro_mental' => 'Filtro mental',
        'desqualificar_positivo' => 'Desqualificar o positivo',
        'raciocinio_emocional' => 'Raciocínio emocional',
        'permissao' => 'Pensamento permissivo',
    ];

    public static function schema(): array
    {
        $string = ['type' => 'STRING'];
        $strings = ['type' => 'ARRAY', 'items' => $string];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'visao_geral' => $string,
                'padroes' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['titulo' => $string, 'descricao' => $string, 'evidencias' => $strings],
                    'required' => ['titulo', 'descricao', 'evidencias'],
                ]],
                'ciclo' => [
                    'type' => 'OBJECT',
                    'properties' => ['situacao' => $string, 'pensamento' => $string, 'emocao' => $string, 'comportamento' => $string],
                    'required' => ['situacao', 'pensamento', 'emocao', 'comportamento'],
                ],
                'pensamentos' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'citacao' => $string,
                        'registro' => $string,
                        'possivel_distorcao' => ['type' => 'STRING', 'enum' => array_keys(self::DISTORTIONS)],
                        'explicacao' => $string,
                    ],
                    'required' => ['citacao', 'registro', 'possivel_distorcao', 'explicacao'],
                ]],
                'gatilhos' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['descricao' => $string, 'emocoes' => $strings],
                    'required' => ['descricao', 'emocoes'],
                ]],
                'compulsoes' => [
                    'type' => 'OBJECT',
                    'properties' => ['ciclo' => $string, 'alto_risco' => $string, 'o_que_ajudou' => $string],
                    'required' => ['ciclo', 'alto_risco', 'o_que_ajudou'],
                ],
                'estrategias' => [
                    'type' => 'OBJECT',
                    'properties' => ['funcionaram' => $strings, 'pouco_efetivas' => $strings],
                    'required' => ['funcionaram', 'pouco_efetivas'],
                ],
                'reconhecimentos' => $strings,
                'perguntas_sessao' => $strings,
                'limitacoes' => $strings,
            ],
            'required' => [
                'visao_geral', 'padroes', 'ciclo', 'pensamentos', 'gatilhos', 'compulsoes',
                'estrategias', 'reconhecimentos', 'perguntas_sessao', 'limitacoes',
            ],
        ];
    }

    /**
     * Normaliza e valida a resposta: descarta evidências e registros com ids
     * que não existem no pacote enviado (contra alucinação) e distorções fora
     * da lista. Lança se faltar a estrutura mínima.
     *
     * @param  array<int, string>  $validIds
     */
    public static function validate(array $data, array $validIds): array
    {
        if (! is_string($data['visao_geral'] ?? null) || trim($data['visao_geral']) === '') {
            throw new GeminiException('A resposta da IA veio incompleta. Tente novamente.', 'visao_geral ausente');
        }

        $text = fn ($value) => is_string($value) ? trim($value) : '';
        $texts = fn ($value) => array_values(array_filter(array_map($text, is_array($value) ? $value : [])));
        $ids = fn ($value) => array_values(array_unique(array_intersect($texts($value), $validIds)));

        return [
            'visao_geral' => $text($data['visao_geral']),
            'padroes' => collect($data['padroes'] ?? [])
                ->map(fn ($p) => ['titulo' => $text($p['titulo'] ?? null), 'descricao' => $text($p['descricao'] ?? null), 'evidencias' => $ids($p['evidencias'] ?? [])])
                ->filter(fn ($p) => $p['titulo'] !== '' && $p['descricao'] !== '')
                ->values()->all(),
            'ciclo' => collect(['situacao', 'pensamento', 'emocao', 'comportamento'])
                ->mapWithKeys(fn ($k) => [$k => $text($data['ciclo'][$k] ?? null)])->all(),
            'pensamentos' => collect($data['pensamentos'] ?? [])
                ->filter(fn ($p) => in_array($p['registro'] ?? null, $validIds, true)
                    && array_key_exists($p['possivel_distorcao'] ?? '', self::DISTORTIONS)
                    && $text($p['citacao'] ?? null) !== '')
                ->map(fn ($p) => [
                    'citacao' => $text($p['citacao']),
                    'registro' => $p['registro'],
                    'possivel_distorcao' => $p['possivel_distorcao'],
                    'explicacao' => $text($p['explicacao'] ?? null),
                ])
                ->values()->all(),
            'gatilhos' => collect($data['gatilhos'] ?? [])
                ->map(fn ($g) => ['descricao' => $text($g['descricao'] ?? null), 'emocoes' => $texts($g['emocoes'] ?? [])])
                ->filter(fn ($g) => $g['descricao'] !== '')
                ->values()->all(),
            'compulsoes' => collect(['ciclo', 'alto_risco', 'o_que_ajudou'])
                ->mapWithKeys(fn ($k) => [$k => $text($data['compulsoes'][$k] ?? null)])->all(),
            'estrategias' => [
                'funcionaram' => $texts($data['estrategias']['funcionaram'] ?? []),
                'pouco_efetivas' => $texts($data['estrategias']['pouco_efetivas'] ?? []),
            ],
            'reconhecimentos' => $texts($data['reconhecimentos'] ?? []),
            'perguntas_sessao' => $texts($data['perguntas_sessao'] ?? []),
            'limitacoes' => $texts($data['limitacoes'] ?? []),
        ];
    }
}
