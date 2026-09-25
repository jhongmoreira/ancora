<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->string('label', 30);
            $table->string('color', 20);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_categories');
    }
};
