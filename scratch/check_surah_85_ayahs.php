<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$reciterId = 25; // Yasser Al-Dosari
$surahNumber = 85;

$ayahs = DB::table('ayahs')->where('surah_id', $surahNumber)->orderBy('ayah_number')->get();

echo "Surah 85 Ayahs Word Timings in local DB:\n";
foreach ($ayahs as $a) {
    $words = DB::table('words')->where('ayah_id', $a->id)->get();
    $timedWords = DB::table('reciter_word_timings')
        ->where('reciter_id', $reciterId)
        ->whereIn('word_id', $words->pluck('id'))
        ->count();
    
    $spokenWords = $words->where('char_type', 'word')->count();
    $status = ($timedWords === $spokenWords) ? 'FULL' : ($timedWords > 0 ? 'PARTIAL' : 'MISSING');
    echo "Ayah {$a->ayah_number} ({$a->verse_key}): {$timedWords}/{$spokenWords} words timed -> [{$status}]\n";
}
