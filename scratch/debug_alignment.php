<?php

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Word;
use App\Services\ContentGeneration\ApprovedContentResolver;
use App\Services\ContentGeneration\ContentNormalizer;

$surahNumber = 54;
$reciterSlug = 'yasser-al-dosari';
$fromAyah = 9;
$toAyah = 17;
$layout = 'reels';

$surah = Surah::where('number', $surahNumber)->first();
$reciter = Reciter::where('slug', $reciterSlug)->first();

$ayahQuery = $surah->ayahs()->where('ayah_number', '>=', $fromAyah)->where('ayah_number', '<=', $toAyah);
$ayahIds = $ayahQuery->pluck('id');

$words = Word::with('ayah')->whereIn('ayah_id', $ayahIds)
    ->orderBy('page_number')
    ->orderBy('line_number')
    ->orderBy('ayah_id')
    ->orderBy('word_index')
    ->get()
    ->all();

$resolver = app(ApprovedContentResolver::class);
$approvedSegments = $resolver->resolve($reciter->id, $surahNumber, $fromAyah, $toAyah, $layout);

$currentIndex = 0;
$wordCount = count($words);

foreach ($approvedSegments as $genSeg) {
    $segmentText = $genSeg->arabic;
    $normSegment = ContentNormalizer::normalizeForMatching($segmentText);
    $normSegmentClean = preg_replace('/\s+/u', '', $normSegment);

    $matchedWords = [];
    $currentMatch = "";
    $startIndex = $currentIndex;

    echo "\n-------------------------------------------------\n";
    echo "Segment Order: {$genSeg->segment_order}\n";
    echo "Segment Text: '{$segmentText}'\n";
    echo "Normalized Clean Segment: '{$normSegmentClean}'\n";

    while ($currentIndex < $wordCount) {
        $word = $words[$currentIndex];
        $matchedWords[] = $word;

        $normTimingWord = ContentNormalizer::normalizeForMatching($word->uthmani_text);
        $normTimingWordClean = preg_replace('/\s+/u', '', $normTimingWord);
        $currentMatch .= $normTimingWordClean;

        echo "  - Word [{$currentIndex}]: '{$word->uthmani_text}' -> Normalized: '{$normTimingWordClean}', CurrentMatch: '{$currentMatch}'\n";

        $currentIndex++;

        if ($currentMatch === $normSegmentClean) {
            echo "  --> MATCH SUCCESS!\n";
            break;
        }

        if (mb_strlen($currentMatch) > mb_strlen($normSegmentClean)) {
            echo "  --> RUNAWAY! (currentMatch len " . mb_strlen($currentMatch) . " > normSegmentClean len " . mb_strlen($normSegmentClean) . ")\n";
            break;
        }
    }
}
