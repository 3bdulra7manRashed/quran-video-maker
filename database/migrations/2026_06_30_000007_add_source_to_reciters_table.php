<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reciters', function (Blueprint $table) {
            $table->string('source', 50)->default('custom')->after('is_default');
        });

        // Set yasser-al-dosari source to quran_com
        DB::table('reciters')->where('slug', 'yasser-al-dosari')->update(['source' => 'quran_com']);
        // Set ali-jaber source to custom
        DB::table('reciters')->where('slug', 'ali-jaber')->update(['source' => 'custom']);
    }

    public function down(): void
    {
        Schema::table('reciters', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
