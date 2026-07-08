<?php

/**
 * Step 1: Prepare dataset for Ali Jaber, Surah 78 (An-Naba')
 * This runs the PrepareDatasetJob synchronously to download audio + timings.
 */

use App\Modules\Dataset\Providers\DatasetProvider;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$slug = 'ali-jaber';
$surahNumber = 78;

// 1. Verify reciter exists
$reciter = Reciter::where('slug', $slug)->first();
if (!$reciter) {
    echo "❌ Reciter '{$slug}' not found!\n";
    exit(1);
}
echo "✅ Reciter: ID={$reciter->id}, Slug={$reciter->slug}, Name={$reciter->name_english}, Source={$reciter->source}\n";

// 2. Verify surah exists
$surah = Surah::where('number', $surahNumber)->first();
if (!$surah) {
    echo "❌ Surah {$surahNumber} not found!\n";
    exit(1);
}
echo "✅ Surah: #{$surah->number} - {$surah->name_arabic} ({$surah->name_english})\n";

// 3. Run PrepareDatasetJob synchronously
echo "\n--- Running PrepareDatasetJob synchronously ---\n";
$provider = app(DatasetProvider::class);

// Check audio availability
$audioAvail = $provider->determineAudioAvailability($slug, $surahNumber);
echo "Audio available before prepare: " . ($audioAvail ? 'YES' : 'NO') . "\n";

// Check timing availability
$timingAvail = $provider->determineTimingsAvailability($slug, $surahNumber);
echo "Timings available before prepare: " . ($timingAvail ? 'YES' : 'NO') . "\n";

// Run the job synchronously
$job = new \App\Jobs\PrepareDatasetJob($slug, $surahNumber);
$job->handle($provider);

echo "\n--- Post-Prepare Checks ---\n";

// 4. Verify audio was downloaded
$audioAvailAfter = $provider->determineAudioAvailability($slug, $surahNumber);
echo "Audio available after prepare: " . ($audioAvailAfter ? '✅ YES' : '❌ NO') . "\n";

// 5. Verify timing was downloaded
$timingAvailAfter = $provider->determineTimingsAvailability($slug, $surahNumber);
echo "Timings available after prepare: " . ($timingAvailAfter ? '✅ YES' : '❌ NO') . "\n";

// 6. Check audio_files table
$audioCount = DB::table('audio_files')
    ->where('reciter_id', $reciter->id)
    ->where('surah_number', $surahNumber)
    ->count();
echo "Audio files in DB for Surah {$surahNumber}: {$audioCount}\n";

// 7. Check word timings
$wordTimingCount = DB::table('reciter_word_timings')
    ->where('reciter_id', $reciter->id)
    ->where('surah_number', $surahNumber)
    ->count();
echo "Word timings in DB for Surah {$surahNumber}: {$wordTimingCount}\n";

// 8. Check line timings
$lineTimingCount = DB::table('reciter_line_timings')
    ->where('reciter_id', $reciter->id)
    ->where('surah_number', $surahNumber)
    ->count();
echo "Line timings in DB for Surah {$surahNumber}: {$lineTimingCount}\n";

// 9. Show sample word timings
if ($wordTimingCount > 0) {
    echo "\nSample word timings (first 5):\n";
    $samples = DB::table('reciter_word_timings')
        ->where('reciter_id', $reciter->id)
        ->where('surah_number', $surahNumber)
        ->orderBy('ayah_number')
        ->orderBy('word_position')
        ->limit(5)
        ->get();
    foreach ($samples as $s) {
        echo "  Ayah {$s->ayah_number}, Word {$s->word_position}: start={$s->start_time}ms, end={$s->end_time}ms\n";
    }
}

echo "\n✅ Preparation complete.\n";
