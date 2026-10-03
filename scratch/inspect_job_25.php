<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$reciterId = 25; // Yasser Al-Dosari
$surahNumber = 85;

echo "=== Checking DB data for Yasser Al-Dosari Surah 85 ===\n";

// 1. Audio file
$audio = DB::table('audio_files')
    ->where('reciter_id', $reciterId)
    ->where('surah_number', $surahNumber)
    ->first();

if ($audio) {
    echo "Audio File: {$audio->file_path} | Duration: {$audio->duration_ms}ms\n";
} else {
    echo "Audio File: NOT FOUND!\n";
}

// 2. Word Timings in DB
$wordTimingsCount = DB::table('reciter_word_timings')
    ->join('words', 'reciter_word_timings.word_id', '=', 'words.id')
    ->join('ayahs', 'words.ayah_id', '=', 'ayahs.id')
    ->where('reciter_word_timings.reciter_id', $reciterId)
    ->where('ayahs.surah_id', $surahNumber)
    ->count();

echo "Word Timings in DB for Surah 85: {$wordTimingsCount}\n";

// 3. Line Timings in DB
$lineTimings = DB::table('reciter_line_timings')
    ->where('reciter_id', $reciterId)
    ->whereIn('page_number', [590])
    ->get();

echo "Line Timings in DB for Page 590: " . $lineTimings->count() . "\n";
foreach ($lineTimings as $lt) {
    echo "  Page {$lt->page_number} Line {$lt->line_number} -> start_ms: {$lt->start_ms}\n";
}

// 4. Check all Line Timings for reciter 25
$allLineTimings = DB::table('reciter_line_timings')
    ->where('reciter_id', $reciterId)
    ->get();

echo "\nTotal Line Timings in DB for Yasser Al-Dosari across all pages: " . $allLineTimings->count() . "\n";
foreach ($allLineTimings as $alt) {
    echo "  Page {$alt->page_number} Line {$alt->line_number} -> start_ms: {$alt->start_ms}\n";
}
