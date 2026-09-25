<?php

namespace Database\Seeders;

use App\Models\Feeling;
use App\Models\MoodCategory;
use Illuminate\Database\Seeder;

class MoodCatalogSeeder extends Seeder
{
    /**
     * Catálogo de humor/sentimentos usado no registro emocional (docs/00, seção 8).
     */
    public function run(): void
    {
        $catalog = [
            [
                'key' => 'positivo',
                'label' => 'Positivo',
                'color' => 'green',
                'order' => 1,
                'feelings' => [
                    'Alegre', 'Grato', 'Amoroso', 'Animado',
                    'Orgulhoso', 'Aliviado', 'Confiante', 'Surpreso',
                ],
            ],
            [
                'key' => 'neutro',
                'label' => 'Neutro',
                'color' => 'gray',
                'order' => 2,
                'feelings' => [
                    'Calmo', 'Indiferente', 'Pensativo', 'Cansado', 'Entediado',
                ],
            ],
            [
                'key' => 'negativo',
                'label' => 'Negativo',
                'color' => 'red',
                'order' => 3,
                'feelings' => [
                    'Ansioso', 'Triste', 'Com raiva', 'Envergonhado',
                    'Culpado', 'Frustrado', 'Assustado', 'Sozinho', 'Surpreso',
                ],
            ],
        ];

        foreach ($catalog as $entry => $data) {
            $category = MoodCategory::updateOrCreate(
                ['key' => $data['key']],
                ['label' => $data['label'], 'color' => $data['color'], 'order' => $data['order']]
            );

            foreach ($data['feelings'] as $order => $name) {
                Feeling::updateOrCreate(
                    ['mood_category_id' => $category->id, 'name' => $name],
                    ['order' => $order + 1]
                );
            }
        }
    }
}
