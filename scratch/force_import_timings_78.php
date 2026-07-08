<?php

/**
 * Force re-download and import timings for Ali Jaber (reciter 24), Surah 78.
 * 
 * The determineTimingsAvailability() uses a shared directory (not per-reciter),
 * so stale files from the old custom reciter (id=2) may exist, causing the
 * prepare pipeline to skip download+import.
 * 
 * This script:
 * 1. Deletes old timing files for Surah 78
 * 2. Downloads fresh timing data from Quran.com API (reciter_id=24)
 * 3. Imports timings into DB for reciter 24
 */

use App\Modules\Dataset\Providers\QuranComDatasetProvider;
use Illuminate\Support\Facades\DB;

$slug = 'ali-jaber';
$surahNumber = 78;

$provider = app(QuranComDatasetProvider::class);

// 1. Delete existing timing files for surah 78
$timingDir = storage_path('app/quran/timings');
echo "Timing directory: {$timingDir}\n";

if (is_dir($timingDir)) {
    $files = glob($timingDir . "/timings_{$surahNumber}_*.json");
    echo "Existing timing files for Surah {$surahNumber}: " . count($files) . "\n";
    foreach ($files as $file) {
        echo "  Deleting: " . basename($file) . "\n";
        unlink($file);
    }
} else {
    echo "Timing directory does not exist.\n";
}

// 2. Verify availability now returns false
$avail = $provider->determineTimingsAvailability($slug, $surahNumber);
echo "\nTimings available after deletion: " . ($avail ? 'YES (unexpected!)' : 'NO (good, will re-download)') . "\n";

// 3. Download fresh timings from Quran.com
echo "\nDownloading timings from Quran.com API (reciter_id=24)...\n";
$provider->downloadTimings($slug, $surahNumber);

// 4. Verify files were downloaded
$filesAfter = glob($timingDir . "/timings_{$surahNumber}_*.json");
echo "Timing files after download: " . count($filesAfter) . "\n";
if (count($filesAfter) > 0) {
    echo "First file: " . basename($filesAfter[0]) . "\n";
    // Show content of first file to verify structure
    $content = json_decode(file_get_contents($filesAfter[0]), true);
    echo "First file content (sample): " . json_encode(array_slice($content, 0, 3)) . "\n";
}

// 5. Import timings into DB
echo "\nImporting timings into DB for reciter 24...\n";
$provider->importTimings($slug, $surahNumber);

// 6. Verify import
$wordTimings = DB::table('reciter_word_timings')
    ->where('reciter_id', 24)
    ->count();
echo "\nWord timings in DB for reciter 24: {$wordTimings}\n";

$lineTimings = DB::table('reciter_line_timings')
    ->where('reciter_id', 24)
    ->count();
echo "Line timings in DB for reciter 24: {$lineTimings}\n";

// 7. Sample word timings
if ($wordTimings > 0) {
    echo "\nSample word timings (first 5):\n";
    $samples = DB::table('reciter_word_timings')
        ->where('reciter_id', 24)
        ->limit(5)
        ->get();
    foreach ($samples as $s) {
        echo "  word_id={$s->word_id}, start={$s->start_ms}ms, end={$s->end_ms}ms\n";
    }
}

echo "\n✅ Done.\n";
