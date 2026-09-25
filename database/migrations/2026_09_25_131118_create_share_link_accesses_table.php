<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_link_accesses', function (Blueprint $table) {
            $table->id();
            // Ligado ao paciente (não ao share_link) para o histórico sobreviver
            // a uma revogação/regeneração do link — ver docs/13.
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->dateTime('accessed_at');

            $table->index(['patient_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_link_accesses');
    }
};
