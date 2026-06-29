<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $Blueprint->foreignId('ayah_id')->constrained('ayahs')->cascadeOnDelete();
            $Blueprint->unsignedSmallInteger('segment_order');
            $Blueprint->unsignedSmallInteger('first_word_index');
            $Blueprint->unsignedSmallInteger('last_word_index');
            $Blueprint->unsignedInteger('start_ms_from_surah');
            $Blueprint->unsignedInteger('end_ms_from_surah');
            $Blueprint->text('glyph_text');
            $Blueprint->text('translation_text')->nullable();
            $Blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
