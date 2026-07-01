<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('render_jobs', function (Blueprint $table) {
            $table->boolean('with_translation')->default(false)->after('max_lines');
            $table->string('translation_source')->nullable()->after('with_translation');
        });
    }

    public function down(): void
    {
        Schema::table('render_jobs', function (Blueprint $table) {
            $table->dropColumn(['with_translation', 'translation_source']);
        });
    }
};
