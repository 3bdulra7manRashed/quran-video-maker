<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$reciterId = 25; // Yasser Al-Dosari
$surahNumber = 85;

$words = DB::table('words')
    ->join('ayahs', 'words.ayah_id', '=', 'ayahs.id')
    ->where('ayahs.surah_id', $surahNumber)
    ->where('words.page_number', 590)
    ->where('words.line_number', 8)
    ->leftJoin('reciter_word_timings', function($join) use ($reciterId) {
        $join->on('words.id', '=', 'reciter_word_timings.word_id')
             ->where('reciter_word_timings.reciter_id', '=', $reciterId);
    })
    ->select(
        'words.id as word_id',
        'words.word_index',
        'words.char_type',
        'words.uthmani_text',
        'ayahs.verse_key',
        'reciter_word_timings.start_ms',
        'reciter_word_timings.end_ms'
    )
    ->get();

echo "Words in Page 590 Line 8:\n";
foreach ($words as $w) {
    echo "  [{$w->verse_key} #{$w->word_index}] ({$w->char_type}) {$w->uthmani_text} -> start: " . ($w->start_ms ?? 'NULL') . "\n";
}

// Also check what verses are in Line 8
$versesInLine8 = $words->pluck('verse_key')->unique();
echo "\nVerses in Line 8: " . implode(', ', $versesInLine8->toArray()) . "\n";

// Check if these verses have timings in reciter_word_timings AT ALL!
foreach ($versesInLine8 as $vk) {
    $vWords = DB::table('words')
        ->join('ayahs', 'words.ayah_id', '=', 'ayahs.id')
        ->where('ayahs.verse_key', $vk)
        ->leftJoin('reciter_word_timings', function($join) use ($reciterId) {
            $join->on('words.id', '=', 'reciter_word_timings.word_id')
                 ->where('reciter_word_timings.reciter_id', '=', $reciterId);
        })
        ->select('words.id', 'words.word_index', 'words.uthmani_text', 'reciter_word_timings.start_ms')
        ->get();
    
    echo "Verse {$vk} all words count: " . $vWords->count() . ", with timings: " . $vWords->whereNotNull('start_ms')->count() . "\n";
}
