<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Dataset\Providers\QuranComDatasetProvider;
use App\Modules\Import\Services\ImportWordTimingsService;
use Illuminate\Support\Facades\DB;

$provider = app(QuranComDatasetProvider::class);
$importer = app(ImportWordTimingsService::class);

echo "Downloading Surah 85 timings for Yasser Al-Dosari...\n";
$provider->downloadTimings('yasser-al-dosari', 85);

echo "Importing timings into database...\n";
$report = $importer->import(null, 25);
print_r($report);

// Verify Ayah 10 in DB
$ayah10 = DB::table('ayahs')->where('verse_key', '85:10')->first();
$timedWords = DB::table('reciter_word_timings')
    ->where('reciter_id', 25)
    ->whereIn('word_id', DB::table('words')->where('ayah_id', $ayah10->id)->pluck('id'))
    ->get();

echo "\nVerification of Ayah 85:10 in reciter_word_timings:\n";
echo "Total timed words for 85:10: " . $timedWords->count() . " / 14\n";
foreach ($timedWords as $tw) {
    echo "  Word ID: {$tw->word_id} -> start: {$tw->start_ms}ms | end: {$tw->end_ms}ms\n";
}
