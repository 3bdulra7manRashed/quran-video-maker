<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ayah_translations', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50);
            $table->unsignedInteger('surah_number');
            $table->unsignedInteger('ayah_number');
            $table->text('text');
            $table->timestamps();

            // Unique index for simple direct lookups
            $table->unique(['source', 'surah_number', 'ayah_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ayah_translations');
    }
};
