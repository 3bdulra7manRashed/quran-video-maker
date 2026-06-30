<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('render_jobs', function (Blueprint $table) {
            $table->string('uuid', 36)->nullable()->after('id');
            $table->unsignedInteger('surah_number')->nullable()->after('surah_id');
            $table->unsignedInteger('from_ayah')->nullable()->after('surah_number');
            $table->unsignedInteger('to_ayah')->nullable()->after('from_ayah');
            $table->string('output_filename')->nullable()->after('output_path');
            $table->timestamp('finished_at')->nullable()->after('completed_at');
            $table->integer('progress')->nullable()->after('finished_at');

            // Make template_id and surah_id nullable so they aren't strictly required
            $table->foreignId('template_id')->nullable()->change();
            $table->foreignId('surah_id')->nullable()->change();

            // Indexes
            $table->unique('uuid');
            $table->index('status');
            $table->index('reciter_id');
            $table->index('surah_number');
        });
    }

    public function down(): void
    {
        Schema::table('render_jobs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['status']);
            $table->dropIndex(['reciter_id']);
            $table->dropIndex(['surah_number']);

            $table->dropColumn([
                'uuid',
                'surah_number',
                'from_ayah',
                'to_ayah',
                'output_filename',
                'finished_at',
                'progress'
            ]);

            $table->foreignId('template_id')->nullable(false)->change();
            $table->foreignId('surah_id')->nullable(false)->change();
        });
    }
};
