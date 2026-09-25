<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('pin_hash');
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};
