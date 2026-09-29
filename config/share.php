<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Acesso compartilhado com a psicóloga (docs/13)
    |--------------------------------------------------------------------------
    |
    | Depois de digitado, o PIN vale por este tempo fixo (navegar ou recarregar
    | a página não prorroga). Passado o prazo, o PIN é pedido de novo, mesmo
    | que o link ainda não tenha expirado.
    |
    */

    'pin_validity_minutes' => (int) env('SHARE_PIN_VALIDITY_MINUTES', 60),

];
