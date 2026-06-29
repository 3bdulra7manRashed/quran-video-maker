<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_tafsirs', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->foreignId('segment_id')->constrained('segments')->cascadeOnDelete();
            $Blueprint->text('tafsir_text');
            $Blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_tafsirs');
    }
};
