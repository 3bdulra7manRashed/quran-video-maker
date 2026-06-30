<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\ReciterLineTiming;
use App\Modules\Quran\Models\Word;
use Illuminate\Support\Facades\Log;

class LineTimingResolver
{
    protected WordTimingResolver $wordTimingResolver;

    public function __construct(WordTimingResolver $wordTimingResolver)
    {
        $this->wordTimingResolver = $wordTimingResolver;
    }

    /**
     * Resolve line timings (line_number => start_ms) for a given surah/reciter.
     *
     * @param Reciter $reciter
     * @param int $surahNumber
     * @param array $words All words in the Surah, sorted in reading order.
     * @param float $totalDuration Total audio duration in seconds.
     * @param array $lineGroups Array of grouped words per line, in order.
     * @return array<int, int> Map of line_number (1-indexed) => start_ms.
     */
    public function resolve(
        Reciter $reciter,
        int $surahNumber,
        array $words,
        float $totalDuration,
        array $lineGroups
    ): array {
        // Case 1: Manual line timings
        $manualTimings = ReciterLineTiming::where('reciter_id', $reciter->id)
            ->where('surah_number', $surahNumber)
            ->get()
            ->keyBy('line_number');

        if ($manualTimings->isNotEmpty()) {
            Log::info("[LineTimingResolver] Using manual line timings for reciter {$reciter->slug}, surah {$surahNumber}.");
            $map = [];
            foreach ($lineGroups as $idx => $group) {
                $lineNumber = $idx + 1; // 1-indexed sequential line number in the Surah
                if ($manualTimings->has($lineNumber)) {
                    $map[$lineNumber] = $manualTimings->get($lineNumber)->start_ms;
                } else {
                    // Fallback to 0 if missing for some reason
                    $map[$lineNumber] = 0;
                }
            }
            return $map;
        }

        // Case 2: Derive from word timings if they exist
        $wordTimingMap = $this->wordTimingResolver->resolve($reciter, $words);
        $hasWordTimings = false;
        foreach ($wordTimingMap as $wId => $timings) {
            if ($timings['start_ms'] !== null) {
                $hasWordTimings = true;
                break;
            }
        }

        if ($hasWordTimings) {
            Log::info("[LineTimingResolver] Deriving line timings from word timings.");
            $map = [];
            foreach ($lineGroups as $idx => $group) {
                $lineNumber = $idx + 1;
                $lineStart = null;

                foreach ($group as $word) {
                    if ($word->char_type === 'word' && isset($wordTimingMap[$word->id]['start_ms'])) {
                        $start = $wordTimingMap[$word->id]['start_ms'];
                        if ($start !== null && ($lineStart === null || $start < $lineStart)) {
                            $lineStart = $start;
                        }
                    }
                }

                $map[$lineNumber] = $lineStart ?? 0;
            }
            return $map;
        }

        // Case 3: Proportional fallback
        Log::info("[LineTimingResolver] No line or word timings found. Falling back to proportional line scheduling.");
        $audioEndMs = (int) round($totalDuration * 1000);
        $spokenWords = array_filter($words, fn($w) => $w->char_type === 'word');
        $spokenCount = count($spokenWords);

        $wordStartMsMap = [];
        if ($spokenCount > 0) {
            $durationPerWord = $audioEndMs / $spokenCount;
            $spokenIndex = 0;
            foreach ($words as $w) {
                if ($w->char_type === 'word') {
                    $wordStartMsMap[$w->id] = (int) round($spokenIndex * $durationPerWord);
                    $spokenIndex++;
                }
            }
        }

        $map = [];
        foreach ($lineGroups as $idx => $group) {
            $lineNumber = $idx + 1;
            $lineStart = null;

            foreach ($group as $word) {
                if ($word->char_type === 'word' && isset($wordStartMsMap[$word->id])) {
                    $start = $wordStartMsMap[$word->id];
                    if ($lineStart === null || $start < $lineStart) {
                        $lineStart = $start;
                    }
                }
            }

            $map[$lineNumber] = $lineStart ?? 0;
        }

        return $map;
    }
}
