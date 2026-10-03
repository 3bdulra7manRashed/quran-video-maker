<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$reciterId = 25; // Yasser Al-Dosari

// Let's fetch Quran.com timings for Surah 85
$url = "https://api.quran.com/api/v4/chapter_recitations/97/85?segments=true";
$res = Http::withoutVerifying()->get($url);
$timestamps = $res->json('audio_file.timestamps') ?? [];

$ayah10Data = null;
foreach ($timestamps as $ts) {
    if ($ts['verse_key'] === '85:10') {
        $ayah10Data = $ts['segments'];
        break;
    }
}

echo "Raw segments for 85:10 (" . count($ayah10Data) . " entries):\n";
print_r($ayah10Data);

// Fetch words of 85:10 from DB
$ayah = DB::table('ayahs')->where('verse_key', '85:10')->first();
$spokenWords = DB::table('words')
    ->where('ayah_id', $ayah->id)
    ->where('char_type', 'word')
    ->orderBy('word_index')
    ->get();

echo "\nDB Spoken Words count: " . $spokenWords->count() . "\n";
foreach ($spokenWords as $w) {
    echo "  #{$w->word_index} {$w->uthmani_text}\n";
}

// Notice: In $ayah10Data, word indices are 1..14, but some repeat!
// For words that repeat, the reciter restarted from word 11 (جهنم) at 58310ms!
// If we collapse duplicate entries by keeping the latest occurrence or expanding bounds:
$wordMap = [];
foreach ($ayah10Data as $seg) {
    $wIdx = $seg[0];
    $start = $seg[1];
    $end = $seg[2];
    
    // If it's a repeated word (e.g. 11 after 13), it means a re-read!
    // We update with the re-read timing
    $wordMap[$wIdx] = ['start_ms' => $start, 'end_ms' => $end];
}

echo "\nCollapsed Word Map (1 to 14):\n";
foreach ($wordMap as $idx => $t) {
    $wordText = $spokenWords[$idx - 1]->uthmani_text ?? '';
    echo "  Word #{$idx} ({$wordText}) -> {$t['start_ms']}ms - {$t['end_ms']}ms\n";
}
