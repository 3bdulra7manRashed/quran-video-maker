<?php

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Ayah;
use App\Services\ContentGeneration\ApprovedContentResolver;
use App\Services\ContentGeneration\GeneratedContentWordAligner;
use App\Services\ContentGeneration\ApprovedSegmentBuilder;
use App\Modules\Rendering\Services\WordTimingResolver;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$surahNumber = 54;
$fromAyah = 9;
$toAyah = 17;
$layout = 'reels';

// 1. Load Surah and Reciter
$surah = Surah::where('number', $surahNumber)->first();
$reciter = Reciter::find(1);
echo "Reciter: {$reciter->name} (slug: {$reciter->slug})\n";

// 2. Load Words
$ayahIds = $surah->ayahs()
    ->where('ayah_number', '>=', $fromAyah)
    ->where('ayah_number', '<=', $toAyah)
    ->pluck('id');

$words = Word::with('ayah')->whereIn('ayah_id', $ayahIds)
    ->orderBy('page_number')
    ->orderBy('line_number')
    ->orderBy('ayah_id')
    ->orderBy('word_index')
    ->get()
    ->all();

echo "Total words loaded from DB: " . count($words) . "\n";

// 3. Load AudioFile
$audioFile = AudioFile::where('reciter_id', $reciter->id)
    ->where('surah_number', $surahNumber)
    ->first();
$totalDuration = $audioFile->duration_ms / 1000.0;
echo "Audio Duration: $totalDuration seconds\n";

// 4. Resolve timings in-memory (same as RenderPipeline)
$timingResolver = app(WordTimingResolver::class);
$timingMap = $timingResolver->resolve($reciter, $words);

foreach ($words as $w) {
    if (isset($timingMap[$w->id])) {
        $w->start_ms_from_surah = $timingMap[$w->id]['start_ms'];
        $w->end_ms_from_surah = $timingMap[$w->id]['end_ms'];
    }
}

// Check bounds for trimming
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

$audioStartMs = 0;
$audioEndMs = (int) round($totalDuration * 1000);

if ($firstSpoken !== null && $lastSpoken !== null) {
    $audioStartMs = $firstSpoken->start_ms_from_surah;
    $audioEndMs = $lastSpoken->end_ms_from_surah;
    echo "Audio start (trimmed): " . ($audioStartMs / 1000.0) . "s, end: " . ($audioEndMs / 1000.0) . "s\n";

    // Normalize timings in-memory relative to trimmed start
    foreach ($words as $w) {
        if ($w->char_type === 'word' && $w->start_ms_from_surah !== null) {
            $w->start_ms_from_surah = max(0, $w->start_ms_from_surah - $audioStartMs);
            $w->end_ms_from_surah = max(0, $w->end_ms_from_surah - $audioStartMs);
        }
    }
    $totalDuration = ($audioEndMs - $audioStartMs) / 1000.0;
}

// 5. Resolve approved content
$resolver = app(ApprovedContentResolver::class);
$approvedSegmentsData = $resolver->resolve($reciter->id, $surahNumber, $fromAyah, $toAyah, $layout);

if (!$approvedSegmentsData) {
    echo "No approved segments found!\n";
    exit(1);
}
echo "Total approved segments from database: " . $approvedSegmentsData->count() . "\n";

// 6. Run word aligner
$aligner = app(GeneratedContentWordAligner::class);
$alignedRanges = $aligner->align($approvedSegmentsData, $words);
echo "Total aligned ranges: " . count($alignedRanges) . "\n";

// 7. Build Segment DTOs
$segmentBuilder = app(ApprovedSegmentBuilder::class);
$segments = $segmentBuilder->build($alignedRanges);
echo "Total segments built: " . count($segments) . "\n";

// Print word timing details of the loaded dataset to check integrity
echo "\n--- TIMING DATASET INTEGRITY CHECK ---\n";
$wordsList = array_values($words);
$spokenCount = 0;
for ($i = 0; $i < count($wordsList); $i++) {
    $w = $wordsList[$i];
    if ($w->char_type === 'word') {
        $spokenCount++;
    }
    echo sprintf(
        "Index: %3d | ID: %5d | CharType: %6s | Uthmani: %-15s | Ayah: %2d | start_ms: %5d | end_ms: %5d\n",
        $i,
        $w->id,
        $w->char_type,
        $w->uthmani_text,
        $w->ayah->ayah_number,
        $w->start_ms_from_surah ?? -1,
        $w->end_ms_from_surah ?? -1
    );
}
echo "Total words: " . count($wordsList) . " (Spoken words: $spokenCount)\n";

// 8. Print Aligned Segment details and detect index drift
echo "\n--- SEGMENT ALIGNMENT & INDEX DRIFT DETECTION ---\n";
$lastEndIndex = -1;
foreach ($alignedRanges as $idx => $range) {
    $order = $range['segmentOrder'];
    $segWords = $range['words'];
    $firstWord = $segWords[0];
    $lastWord = end($segWords);

    // Find indices of first and last matched words in the original $words array
    $startIndexInArray = -1;
    $endIndexInArray = -1;
    foreach ($wordsList as $key => $w) {
        if ($w->id === $firstWord->id) {
            $startIndexInArray = $key;
        }
        if ($w->id === $lastWord->id) {
            $endIndexInArray = $key;
        }
    }

    $segmentDto = $segments[$idx];

    echo "Segment #$order:\n";
    echo "  Approved Arabic: " . $approvedSegmentsData->where('segment_order', $order)->first()->arabic . "\n";
    echo "  Matched Words: " . implode(' ', array_map(fn($w) => $w->uthmani_text, $segWords)) . "\n";
    echo "  Start Index in Words Array: $startIndexInArray, End Index: $endIndexInArray\n";
    echo "  Start Time (from Word DTO): {$firstWord->start_ms_from_surah} ms, End Time: {$lastWord->end_ms_from_surah} ms\n";
    echo "  Segment DTO Timings: Start: {$segmentDto->startMs} ms, End: {$segmentDto->endMs} ms\n";

    if ($lastEndIndex !== -1) {
        $expectedStart = $lastEndIndex + 1;
        if ($startIndexInArray !== $expectedStart) {
            echo "  ⚠️ DRIFT DETECTED! Expected start index: $expectedStart, got: $startIndexInArray\n";
        }
    }
    $lastEndIndex = $endIndexInArray;
}
