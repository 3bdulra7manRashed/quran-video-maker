<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('words', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->foreignId('ayah_id')->constrained('ayahs')->cascadeOnDelete();
            $Blueprint->unsignedSmallInteger('word_index');
            $Blueprint->string('char_type');
            $Blueprint->string('plain_text')->nullable();
            $Blueprint->string('uthmani_text')->nullable();
            $Blueprint->string('glyph_text');
            $Blueprint->unsignedSmallInteger('page_number');
            $Blueprint->unsignedSmallInteger('line_number')->nullable();
            $Blueprint->text('translation_en')->nullable();
            $Blueprint->unsignedInteger('start_ms_from_surah')->nullable();
            $Blueprint->unsignedInteger('end_ms_from_surah')->nullable();
            $Blueprint->timestamps();

            $Blueprint->unique(['ayah_id', 'word_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
