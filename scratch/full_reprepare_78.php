<?php

/**
 * Full re-preparation of Ali Jaber Surah 78:
 * 1. Delete old audio file (may be from wrong URL)
 * 2. Delete old audio_files DB record
 * 3. Run PrepareDatasetJob synchronously (downloads audio from ali_jaber URL + attempts timing download)
 * 4. Verify everything is ready for rendering
 */

use App\Modules\Dataset\Providers\DatasetProvider;
use App\Modules\Dataset\Providers\QuranComDatasetProvider;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Support\Facades\DB;

$slug = 'ali-jaber';
$surahNumber = 78;

$pathResolver = app(QuranPathResolver::class);
$provider = app(DatasetProvider::class);

// 1. Delete existing audio file on disk
$audioPath = $pathResolver->audio("{$slug}/078.mp3");
echo "Audio path: {$audioPath}\n";
if (file_exists($audioPath)) {
    echo "Deleting existing audio file...\n";
    unlink($audioPath);
}

// 2. Delete audio_files DB record
$reciter = Reciter::where('slug', $slug)->first();
echo "Reciter: ID={$reciter->id}, Source={$reciter->source}\n";

DB::table('audio_files')->where('reciter_id', $reciter->id)->where('surah_number', $surahNumber)->delete();
echo "Deleted audio_files DB record.\n";

// 3. Delete old timing files on disk (for surah 78)
$timingDir = storage_path('app/quran/timings');
$timingFiles = glob($timingDir . "/timings_{$surahNumber}_*.json");
echo "\nDeleting " . count($timingFiles) . " old timing files for Surah {$surahNumber}...\n";
foreach ($timingFiles as $f) {
    unlink($f);
}

// 4. Run PrepareDatasetJob synchronously
echo "\n--- Running PrepareDatasetJob synchronously ---\n";
$job = new \App\Jobs\PrepareDatasetJob($slug, $surahNumber);
$job->handle($provider);

// 5. Verify
echo "\n--- Verification ---\n";

// Audio
$audioExists = file_exists($audioPath) && filesize($audioPath) > 0;
echo "Audio file exists: " . ($audioExists ? '✅ YES (' . round(filesize($audioPath) / 1024 / 1024, 2) . ' MB)' : '❌ NO') . "\n";

$audioRecord = DB::table('audio_files')
    ->where('reciter_id', $reciter->id)
    ->where('surah_number', $surahNumber)
    ->first();
if ($audioRecord) {
    echo "Audio DB record: duration={$audioRecord->duration_ms}ms, path={$audioRecord->file_path}\n";
} else {
    echo "❌ No audio DB record\n";
}

// Word timings
$wordTimings = DB::table('reciter_word_timings')
    ->where('reciter_id', $reciter->id)
    ->count();
echo "Word timings in DB: {$wordTimings}\n";

// Line timings
$lineTimings = DB::table('reciter_line_timings')
    ->where('reciter_id', $reciter->id)
    ->count();
echo "Line timings in DB: {$lineTimings}\n";

// Timing files on disk
$newTimingFiles = glob($timingDir . "/timings_{$surahNumber}_*.json");
echo "Timing files on disk for Surah {$surahNumber}: " . count($newTimingFiles) . "\n";

if ($wordTimings == 0 && count($newTimingFiles) == 0) {
    echo "\n⚠️  No word-level timing segments available for Ali Jaber.\n";
    echo "The system will use PROPORTIONAL FALLBACK timing when rendering.\n";
    echo "This is expected — Quran.com does not provide word-level segments for this reciter.\n";
}

echo "\n✅ Preparation complete. Ready for rendering.\n";
