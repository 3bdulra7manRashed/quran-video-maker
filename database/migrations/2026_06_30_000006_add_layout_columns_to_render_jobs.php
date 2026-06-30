<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('render_jobs', function (Blueprint $table) {
            $table->string('layout', 50)->default('reels')->after('surah_number');
            $table->unsignedInteger('max_lines')->default(1)->after('layout');
        });
    }

    public function down(): void
    {
        Schema::table('render_jobs', function (Blueprint $table) {
            $table->dropColumn(['layout', 'max_lines']);
        });
    }
};
