<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compulsion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compulsion_id')->constrained()->cascadeOnDelete();
            $table->dateTime('occurred_at');
            // 'gave_in' (cedeu) | 'resisted' (resistiu) — docs/14.
            $table->string('outcome', 16);
            $table->unsignedTinyInteger('urge_intensity');
            $table->text('trigger');
            $table->text('automatic_thought')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('coping_strategy')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'occurred_at']);
            $table->index(['compulsion_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compulsion_logs');
    }
};
