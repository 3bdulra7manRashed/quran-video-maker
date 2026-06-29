<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_files', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->foreignId('reciter_id')->constrained('reciters')->cascadeOnDelete();
            $Blueprint->unsignedSmallInteger('surah_number');
            $Blueprint->string('file_path');
            $Blueprint->unsignedInteger('duration_ms')->nullable();
            $Blueprint->timestamps();

            $Blueprint->unique(['reciter_id', 'surah_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_files');
    }
};
