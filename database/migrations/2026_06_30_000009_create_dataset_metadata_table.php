<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reciter_id')->constrained('reciters')->cascadeOnDelete();
            $table->unsignedInteger('surah_number');
            $table->unsignedInteger('from_ayah')->nullable();
            $table->unsignedInteger('to_ayah')->nullable();
            $table->timestamps();

            $table->unique(['reciter_id', 'surah_number']);
            $table->index('reciter_id');
            $table->index('surah_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_metadata');
    }
};
