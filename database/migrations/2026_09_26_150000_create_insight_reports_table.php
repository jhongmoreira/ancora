<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            // Opção escolhida no seletor ('7d', '15d', '30d', '90d', 'month', 'custom') e as datas resolvidas.
            $table->string('period_preset', 16);
            $table->date('period_start');
            $table->date('period_end');
            // 'completed' | 'failed' — docs/15.
            $table->string('status', 16);
            $table->string('model', 64)->nullable();
            $table->string('prompt_version', 16);
            // Números calculados no app (não pela IA), exibidos na tela.
            $table->json('stats');
            // Pacote anonimizado exatamente como foi enviado ao Gemini.
            $table->json('payload');
            // Resposta validada da IA (nula quando falhou).
            $table->json('content')->nullable();
            $table->boolean('risk_flag')->default(false);
            $table->json('risk_record_ids')->nullable();
            $table->json('usage')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('ai_consent_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('ai_consent_at');
        });

        Schema::dropIfExists('insight_reports');
    }
};
