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
        Schema::table('audio_files', function (Blueprint $table) {
            $table->string('format', 20)->nullable()->after('duration_ms');
            $table->unsignedBigInteger('file_size')->nullable()->after('format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_files', function (Blueprint $table) {
            $table->dropColumn(['format', 'file_size']);
        });
    }
};
