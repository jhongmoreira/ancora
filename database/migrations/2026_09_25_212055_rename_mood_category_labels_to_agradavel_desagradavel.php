<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Troca os rótulos exibidos de "Positivo"/"Negativo" para "Agradável"/
 * "Desagradável". Só o `label` muda — as `key` internas (`positivo`/
 * `negativo`) continuam iguais, então código e registros existentes não
 * são afetados.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('mood_categories')->where('key', 'positivo')->update(['label' => 'Agradável']);
        DB::table('mood_categories')->where('key', 'negativo')->update(['label' => 'Desagradável']);
    }

    public function down(): void
    {
        DB::table('mood_categories')->where('key', 'positivo')->update(['label' => 'Positivo']);
        DB::table('mood_categories')->where('key', 'negativo')->update(['label' => 'Negativo']);
    }
};
