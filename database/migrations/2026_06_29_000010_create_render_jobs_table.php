<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('render_jobs', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $Blueprint->foreignId('reciter_id')->constrained('reciters')->cascadeOnDelete();
            $Blueprint->foreignId('surah_id')->constrained('surahs')->cascadeOnDelete();
            $Blueprint->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $Blueprint->string('output_path')->nullable();
            $Blueprint->text('error_message')->nullable();
            $Blueprint->timestamp('started_at')->nullable();
            $Blueprint->timestamp('completed_at')->nullable();
            $Blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('render_jobs');
    }
};
