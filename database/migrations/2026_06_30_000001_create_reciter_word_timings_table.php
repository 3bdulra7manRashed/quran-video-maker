<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reciter_word_timings', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->foreignId('reciter_id')->constrained('reciters')->cascadeOnDelete();
            $Blueprint->foreignId('word_id')->constrained('words')->cascadeOnDelete();
            $Blueprint->unsignedInteger('start_ms');
            $Blueprint->unsignedInteger('end_ms');
            $Blueprint->timestamps();

            $Blueprint->unique(['reciter_id', 'word_id']);
            $Blueprint->index(['reciter_id', 'word_id']);
            $Blueprint->index('reciter_id');
            $Blueprint->index('word_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reciter_word_timings');
    }
};
