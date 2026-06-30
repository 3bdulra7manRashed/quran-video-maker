<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reciter_line_timings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reciter_id')->constrained('reciters')->cascadeOnDelete();
            $table->unsignedInteger('surah_number');
            $table->unsignedInteger('line_number'); // Global line number or page-specific? Wait!
            // Wait, does the unique constraint use line_number?
            // "UNIQUE(reciter_id, surah_number, line_number)"
            // Yes! This refers to the sequential line number of that surah, or the page-specific line number.
            // Let's store the sequential line number of the surah (1-indexed) which maps to the JSON line timing format.
            $table->unsignedInteger('start_ms');
            $table->timestamps();

            $table->unique(['reciter_id', 'surah_number', 'line_number']);
            $table->index('reciter_id');
            $table->index('surah_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reciter_line_timings');
    }
};
