<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Limites de geração de relatórios de insights (docs/15)
    |--------------------------------------------------------------------------
    |
    | Protegem a cota gratuita da API do Gemini e evitam cliques repetidos
    | na geração automática. Configuráveis pra ajustar sem deploy de código
    | quando a cota disponível mudar (ex.: ao habilitar faturamento).
    |
    */

    'max_reports_per_day' => (int) env('INSIGHTS_MAX_REPORTS_PER_DAY', 10),

    'cooldown_seconds' => (int) env('INSIGHTS_COOLDOWN_SECONDS', 300),

];
