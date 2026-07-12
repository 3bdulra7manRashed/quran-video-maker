<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$reciters = DB::table('reciters')->get();
foreach ($reciters as $reciter) {
    echo "Reciter: {$reciter->slug} (ID: {$reciter->id})\n";
    
    // Check how many words have timings for this reciter
    $count = DB::table('reciter_word_timings')
        ->where('reciter_id', $reciter->id)
        ->count();
    echo "  Total timed words: {$count}\n";
    
    // Check Surah 55 timings
    $surah55Count = DB::table('reciter_word_timings')
        ->join('words', 'reciter_word_timings.word_id', '=', 'words.id')
        ->join('ayahs', 'words.ayah_id', '=', 'ayahs.id')
        ->join('surahs', 'ayahs.surah_id', '=', 'surahs.id')
        ->where('reciter_word_timings.reciter_id', $reciter->id)
        ->where('surahs.number', 55)
        ->count();
    echo "  Surah 55 timed words: {$surah55Count}\n";
    
    // Check if there are any timings for ayah 55
    $ayah55Count = DB::table('reciter_word_timings')
        ->join('words', 'reciter_word_timings.word_id', '=', 'words.id')
        ->join('ayahs', 'words.ayah_id', '=', 'ayahs.id')
        ->join('surahs', 'ayahs.surah_id', '=', 'surahs.id')
        ->where('reciter_word_timings.reciter_id', $reciter->id)
        ->where('surahs.number', 55)
        ->where('ayahs.ayah_number', 55)
        ->count();
    echo "  Surah 55 Ayah 55 timed words: {$ayah55Count}\n";
    
    if ($ayah55Count > 0) {
        $timings = DB::table('reciter_word_timings')
            ->join('words', 'reciter_word_timings.word_id', '=', 'words.id')
            ->join('ayahs', 'words.ayah_id', '=', 'ayahs.id')
            ->join('surahs', 'ayahs.surah_id', '=', 'surahs.id')
            ->where('reciter_word_timings.reciter_id', $reciter->id)
            ->where('surahs.number', 55)
            ->where('ayahs.ayah_number', 55)
            ->orderBy('words.word_index')
            ->select('words.word_index', 'words.uthmani_text', 'reciter_word_timings.start_ms', 'reciter_word_timings.end_ms')
            ->get();
        foreach ($timings as $t) {
            echo "    Word Index {$t->word_index} ({$t->uthmani_text}): {$t->start_ms}ms - {$t->end_ms}ms\n";
        }
    }
}
