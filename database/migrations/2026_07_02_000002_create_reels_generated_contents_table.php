<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reels_generated_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reciter_id')->constrained('reciters')->cascadeOnDelete();
            $table->unsignedInteger('surah_number');
            $table->unsignedInteger('start_ayah');
            $table->unsignedInteger('end_ayah');
            $table->string('layout_type', 50);
            $table->unsignedInteger('segment_order');
            $table->text('arabic');
            $table->text('translation');
            $table->text('tafsir');
            $table->string('approval_status', 50)->default('pending');
            $table->unsignedInteger('content_version')->default(1);
            $table->string('generator_type', 50)->default('manual');
            $table->string('generator_model', 100)->nullable();
            $table->unsignedInteger('generator_latency_ms')->nullable();
            $table->string('prompt_version', 50)->nullable();
            $table->string('prompt_hash', 100)->nullable();
            $table->json('source_json')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            // Unique composite index
            $table->unique(
                ['reciter_id', 'surah_number', 'start_ayah', 'end_ayah', 'layout_type', 'content_version', 'segment_order'],
                'reels_gen_content_uniq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reels_generated_contents');
    }
};
