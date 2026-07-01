<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reciter_line_timings', function (Blueprint $table) {
            // Add page_number as nullable first to allow existing data preservation safely
            $table->unsignedInteger('page_number')->nullable()->after('surah_number');
        });

        Schema::table('reciter_line_timings', function (Blueprint $table) {
            // Drop old unique constraint
            $table->dropUnique(['reciter_id', 'surah_number', 'line_number']);
        });

        Schema::table('reciter_line_timings', function (Blueprint $table) {
            // Add new unique constraint and index
            $table->unique(['reciter_id', 'surah_number', 'page_number', 'line_number'], 'reciter_line_timings_composite_unique');
            $table->index('page_number');
        });
    }

    public function down(): void
    {
        Schema::table('reciter_line_timings', function (Blueprint $table) {
            $table->dropUnique('reciter_line_timings_composite_unique');
            $table->dropColumn('page_number');
            $table->unique(['reciter_id', 'surah_number', 'line_number']);
        });
    }
};
