<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ayahs', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->foreignId('surah_id')->constrained('surahs')->cascadeOnDelete();
            $Blueprint->string('verse_key');
            $Blueprint->unsignedSmallInteger('ayah_number');
            $Blueprint->unsignedSmallInteger('page_number');
            $Blueprint->unsignedTinyInteger('juz_number');
            $Blueprint->unsignedTinyInteger('hizb_number');
            $Blueprint->timestamps();

            $Blueprint->unique(['surah_id', 'ayah_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayahs');
    }
};
