<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surahs', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->unsignedSmallInteger('number')->unique();
            $Blueprint->string('name_arabic');
            $Blueprint->string('name_simple');
            $Blueprint->string('surah_glyph')->nullable();
            $Blueprint->unsignedSmallInteger('verses_count');
            $Blueprint->unsignedSmallInteger('start_page');
            $Blueprint->unsignedSmallInteger('end_page');
            $Blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surahs');
    }
};
