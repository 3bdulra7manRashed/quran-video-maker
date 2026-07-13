<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\PrepareDatasetJob;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Ayah;
use App\Modules\Quran\Models\Word;

// Find first reciter
$reciter = Reciter::first();
if (!$reciter) {
    die("No reciter found in database. Run check_db.php to verify.\n");
}

$reciterSlug = $reciter->slug;
$surahNumber = 6;

echo "Preparing Surah {$surahNumber} for reciter '{$reciterSlug}'...\n";

// Clear any existing ayahs/words for Surah 6 to test a clean import
// Wait, we checked that Surah 6 had 0 ayahs anyway, but let's make sure
$ayahIds = Ayah::whereHas('surah', function($query) use ($surahNumber) {
    $query->where('number', $surahNumber);
})->pluck('id');

if ($ayahIds->isNotEmpty()) {
    echo "Clearing existing Surah {$surahNumber} data from DB...\n";
    Word::whereIn('ayah_id', $ayahIds)->delete();
    Ayah::whereIn('id', $ayahIds)->delete();
}

// Remove downloaded JSON from disk to force a fresh download
$destFile = storage_path("app/quran/glyph/surah_{$surahNumber}.json");
if (file_exists($destFile)) {
    echo "Deleting existing download file: {$destFile}\n";
    unlink($destFile);
}
$metaFile = storage_path("app/quran/glyph/metadata/surah_{$surahNumber}.metadata.json");
if (file_exists($metaFile)) {
    unlink($metaFile);
}

// Dispatch / execute job
try {
    $job = new PrepareDatasetJob($reciterSlug, $surahNumber);
    app()->call([$job, 'handle']);
    echo "PrepareDatasetJob completed successfully!\n";
} catch (\Throwable $e) {
    echo "PrepareDatasetJob FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Check database results
$ayahCount = Ayah::whereHas('surah', function($query) use ($surahNumber) {
    $query->where('number', $surahNumber);
})->count();
$wordCount = Word::whereHas('ayah.surah', function($query) use ($surahNumber) {
    $query->where('number', $surahNumber);
})->count();

echo "Database status for Surah 6:\n";
echo "  - Ayahs count: {$ayahCount}\n";
echo "  - Words count: {$wordCount}\n";
