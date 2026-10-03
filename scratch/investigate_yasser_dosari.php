<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$reciter = DB::table('reciters')->where('slug', 'yasser-al-dosari')->orWhere('id', 25)->first();

if (!$reciter) {
    echo "Reciter Yasser Al-Dosari not found in database.\n";
    exit;
}

echo "=== Reciter Profile in DB ===\n";
echo "ID: {$reciter->id}\n";
echo "Slug: {$reciter->slug}\n";
echo "QDC Reciter ID: " . ($reciter->qdc_reciter_id ?? 'NULL') . "\n";
echo "Source: " . ($reciter->source ?? 'NULL') . "\n";
echo "Has Word Timings Flag: " . ($reciter->has_word_timings ?? 'NULL') . "\n\n";

$timingsCount = DB::table('reciter_word_timings')
    ->where('reciter_id', $reciter->id)
    ->count();

echo "Total Word Timings in DB: {$timingsCount}\n\n";

// Let's see which surahs have word timings for Yasser Al-Dosari
$surahBreakdown = DB::table('reciter_word_timings')
    ->join('words', 'reciter_word_timings.word_id', '=', 'words.id')
    ->join('ayahs', 'words.ayah_id', '=', 'ayahs.id')
    ->join('surahs', 'ayahs.surah_id', '=', 'surahs.id')
    ->where('reciter_word_timings.reciter_id', $reciter->id)
    ->select(
        'surahs.number as surah_number',
        'surahs.name_arabic as surah_name',
        DB::raw('COUNT(DISTINCT ayahs.id) as ayahs_with_timings'),
        DB::raw('COUNT(reciter_word_timings.id) as words_count')
    )
    ->groupBy('surahs.number', 'surahs.name_arabic')
    ->orderBy('surahs.number')
    ->get();

echo "Surahs breakdown for Yasser Al-Dosari in DB (" . $surahBreakdown->count() . " Surahs):\n";
echo str_repeat('-', 65) . "\n";
printf("%-8s | %-20s | %-12s | %-12s\n", "Surah #", "Name", "Ayahs Count", "Words Count");
echo str_repeat('-', 65) . "\n";

foreach ($surahBreakdown as $sb) {
    printf("%-8d | %-20s | %-12d | %-12d\n", $sb->surah_number, $sb->surah_name, $sb->ayahs_with_timings, $sb->words_count);
}

// Sample words with timing
echo "\nSample word timings (Surah 1 Al-Fatiha or first available):\n";
$firstSurah = $surahBreakdown->first();
if ($firstSurah) {
    $samples = DB::table('reciter_word_timings')
        ->join('words', 'reciter_word_timings.word_id', '=', 'words.id')
        ->join('ayahs', 'words.ayah_id', '=', 'ayahs.id')
        ->where('reciter_word_timings.reciter_id', $reciter->id)
        ->where('ayahs.surah_id', $firstSurah->surah_number)
        ->select('ayahs.verse_key', 'words.word_index', 'words.uthmani_text', 'reciter_word_timings.start_ms', 'reciter_word_timings.end_ms')
        ->limit(10)
        ->get();

    foreach ($samples as $s) {
        echo "  [{$s->verse_key} #{$s->word_index}] {$s->uthmani_text} -> {$s->start_ms}ms - {$s->end_ms}ms\n";
    }
}
