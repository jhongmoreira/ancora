<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_dispatch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->date('sent_date');
            $table->dateTime('sent_at');

            $table->unique(['reminder_id', 'sent_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_dispatch_logs');
    }
};
