<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compulsion_log_feeling', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compulsion_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feeling_id')->constrained()->cascadeOnDelete();
            // 'before' (antecedente) | 'after' (consequência) — docs/14.
            $table->string('moment', 8);

            $table->unique(['compulsion_log_id', 'feeling_id', 'moment'], 'compulsion_log_feeling_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compulsion_log_feeling');
    }
};
