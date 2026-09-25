<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emotion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mood_category_id')->constrained();
            $table->dateTime('occurred_at');
            $table->unsignedTinyInteger('intensity')->nullable();
            $table->text('situation');
            $table->text('action');
            $table->text('automatic_thought')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'occurred_at']);
            $table->index(['patient_id', 'mood_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emotion_logs');
    }
};
