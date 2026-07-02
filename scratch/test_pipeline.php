<?php

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Rendering\Services\WordTimingResolver;
use App\Modules\Segmentation\Services\SegmentationService;
use App\Services\ContentGeneration\ApprovedContentResolver;
use App\Services\ContentGeneration\GeneratedContentWordAligner;
use App\Services\ContentGeneration\ApprovedSegmentBuilder;

$surahNumber = 54;
$reciterSlug = 'yasser-al-dosari';
$fromAyah = 9;
$toAyah = 17;
$layout = 'reels';
$maxLines = 1;

$surah = Surah::where('number', $surahNumber)->first();
$reciter = Reciter::where('slug', $reciterSlug)->first();
$audioFile = AudioFile::where('reciter_id', $reciter->id)
    ->where('surah_number', $surahNumber)
    ->first();

$totalDuration = $audioFile->duration_ms / 1000.0;

$ayahQuery = $surah->ayahs()->where('ayah_number', '>=', $fromAyah)->where('ayah_number', '<=', $toAyah);
$ayahIds = $ayahQuery->pluck('id');

$words = Word::with('ayah')->whereIn('ayah_id', $ayahIds)
    ->orderBy('page_number')
    ->orderBy('line_number')
    ->orderBy('ayah_id')
    ->orderBy('word_index')
    ->get()
    ->all();

$timingResolver = app(WordTimingResolver::class);
$timingMap = $timingResolver->resolve($reciter, $words);
foreach ($words as $w) {
    if (isset($timingMap[$w->id])) {
        $w->start_ms_from_surah = $timingMap[$w->id]['start_ms'];
        $w->end_ms_from_surah = $timingMap[$w->id]['end_ms'];
    }
}

$hasTimings = false;
foreach ($words as $w) {
    if ($w->char_type === 'word' && $w->start_ms_from_surah !== null) {
        $hasTimings = true;
        break;
    }
}

$audioStartMs = 0;
$audioEndMs = (int) round($totalDuration * 1000);

if ($hasTimings) {
    $firstSpoken = null;
    $lastSpoken = null;
    foreach ($words as $w) {
        if ($w->char_type === 'word' && $w->start_ms_from_surah !== null) {
            if ($firstSpoken === null || $w->start_ms_from_surah < $firstSpoken->start_ms_from_surah) {
                $firstSpoken = $w;
            }
            if ($lastSpoken === null || $w->end_ms_from_surah > $lastSpoken->end_ms_from_surah) {
                $lastSpoken = $w;
            }
        }
    }

    if ($firstSpoken !== null && $lastSpoken !== null) {
        $audioStartMs = $firstSpoken->start_ms_from_surah;
        $audioEndMs = $lastSpoken->end_ms_from_surah;

        foreach ($words as $w) {
            if ($w->char_type === 'word' && $w->start_ms_from_surah !== null) {
                $w->start_ms_from_surah = max(0, $w->start_ms_from_surah - $audioStartMs);
                $w->end_ms_from_surah = max(0, $w->end_ms_from_surah - $audioStartMs);
            }
        }
        $totalDuration = ($audioEndMs - $audioStartMs) / 1000.0;
    }
}

echo "=== INPUT WORDS AND TIMINGS ===\n";
foreach ($words as $idx => $w) {
    echo sprintf("[%d] Word ID: %d, Text: %s, CharType: %s, Ayah: %d, Start: %s ms, End: %s ms\n", 
        $idx, $w->id, $w->uthmani_text, $w->char_type, $w->ayah->ayah_number, 
        $w->start_ms_from_surah ?? 'NULL', $w->end_ms_from_surah ?? 'NULL');
}

echo "\n=== DEFAULT SEGMENTATION ===\n";
$segmentationService = app(SegmentationService::class);
// Let's clone words because they are modified in-place
$wordsClone = array_map(fn($w) => clone $w, $words);
$defaultSegments = $segmentationService->segment($wordsClone, $totalDuration, $layout, $maxLines, $reciter, $surahNumber);

foreach ($defaultSegments as $seg) {
    $ayahNumbers = array_unique(array_map(fn($fw) => $fw->ayah->ayah_number, $seg->words));
    echo sprintf("Seg %d: Start: %d ms, End: %d ms, Duration: %d ms, Words: %s, Ayahs: %s\n",
        $seg->index, $seg->startMs, $seg->endMs, $seg->durationMs,
        implode(' ', array_map(fn($fw) => $fw->uthmani_text, $seg->words)),
        implode(',', $ayahNumbers)
    );
}

echo "\n=== APPROVED GENERATED CONTENT SEGMENTATION ===\n";
$resolver = app(ApprovedContentResolver::class);
$approvedSegmentsData = $resolver->resolve($reciter->id, $surahNumber, $fromAyah, $toAyah, $layout);

if ($approvedSegmentsData !== null) {
    echo "Found " . $approvedSegmentsData->count() . " approved segments in DB.\n";
    $aligner = app(GeneratedContentWordAligner::class);
    $segmentBuilder = app(ApprovedSegmentBuilder::class);
    
    $alignedRanges = $aligner->align($approvedSegmentsData, $words);
    $aiSegments = $segmentBuilder->build($alignedRanges);

    foreach ($aiSegments as $seg) {
        $ayahNumbers = array_unique(array_map(fn($fw) => $fw->ayah->ayah_number, $seg->words));
        echo sprintf("Seg %d: Start: %d ms, End: %d ms, Duration: %d ms, Words: %s, Ayahs: %s\n",
            $seg->index, $seg->startMs, $seg->endMs, $seg->endMs - $seg->startMs,
            implode(' ', array_map(fn($fw) => $fw->uthmani_text, $seg->words)),
            implode(',', $ayahNumbers)
        );
    }
} else {
    echo "No approved content found in database.\n";
}
