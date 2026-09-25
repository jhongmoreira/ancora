<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emotion_log_feeling', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emotion_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feeling_id')->constrained()->cascadeOnDelete();

            $table->unique(['emotion_log_id', 'feeling_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emotion_log_feeling');
    }
};
