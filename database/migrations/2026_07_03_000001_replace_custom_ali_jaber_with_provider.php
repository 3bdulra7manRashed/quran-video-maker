<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace custom Ali Jaber (id=2, source=custom) with provider-backed reciter.
     *
     * Steps:
     * 1. Delete all dependent records referencing reciter_id=2
     * 2. Delete the custom reciter record (id=2)
     * 3. Insert provider-backed reciter with slug=ali-jaber, source=quran_com
     *
     * The provider (QuranComDatasetProvider) will handle audio downloads,
     * timing downloads, and imports automatically via the prepare pipeline,
     * just like yasser-al-dosari.
     */
    public function up(): void
    {
        // 1. Delete all dependent records for the custom reciter (id=2)
        DB::table('render_jobs')->where('reciter_id', 2)->delete();
        DB::table('reels_generated_contents')->where('reciter_id', 2)->delete();
        DB::table('reciter_word_timings')->where('reciter_id', 2)->delete();
        DB::table('reciter_line_timings')->where('reciter_id', 2)->delete();
        DB::table('dataset_metadata')->where('reciter_id', 2)->delete();
        DB::table('audio_files')->where('reciter_id', 2)->delete();

        // 2. Delete the custom reciter record
        DB::table('reciters')->where('id', 2)->delete();

        // 3. Insert the provider-backed Ali Jaber reciter
        //    Using slug=ali-jaber so existing UI references work.
        //    Using source=quran_com so the PrepareDatasetJob pipeline
        //    downloads audio and timings automatically.
        DB::table('reciters')->insert([
            'id'           => 24,
            'name_arabic'  => 'علي جابر',
            'name_english' => 'Ali Jaber',
            'slug'         => 'ali-jaber',
            'is_default'   => false,
            'source'       => 'quran_com',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        // Remove the provider-backed reciter
        DB::table('reciters')->where('id', 24)->delete();

        // Re-insert the custom reciter
        DB::table('reciters')->insert([
            'id'           => 2,
            'name_arabic'  => 'علي جابر',
            'name_english' => 'Ali Jaber',
            'slug'         => 'ali-jaber',
            'is_default'   => false,
            'source'       => 'custom',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
};
