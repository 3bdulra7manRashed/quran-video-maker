<?php

namespace App\Services\ContentGeneration;

use App\Modules\Segmentation\DTO\Segment;

class ApprovedSegmentBuilder
{
    /**
     * Build Segment DTOs from aligned ranges.
     *
     * @param array $alignedRanges
     * @return Segment[]
     */
    public function build(array $alignedRanges): array
    {
        $segments = [];

        foreach ($alignedRanges as $range) {
            $words = $range['words'];

            // 1. Detect missing timings
            $missingWordIds = [];
            $missingWords = [];
            foreach ($words as $w) {
                if ($w->char_type === 'word' && ($w->start_ms_from_surah === null || $w->end_ms_from_surah === null)) {
                    $missingWordIds[] = $w->id;
                    $missingWords[] = $w->uthmani_text;
                }
            }

            if (!empty($missingWordIds)) {
                // Log MISSING TIMINGS
                \Illuminate\Support\Facades\Log::warning('MISSING TIMINGS', [
                    'segment_order' => $range['segmentOrder'],
                    'start_index' => $words[0]->id ?? null,
                    'end_index' => end($words)->id ?? null,
                    'missing_word_ids' => $missingWordIds,
                    'missing_words' => $missingWords,
                ]);

                throw new \App\Services\ContentGeneration\Exceptions\MissingTimingException(
                    "Missing word timings for segment #{$range['segmentOrder']}.",
                    $range['segmentOrder'],
                    $words[0]->id ?? 0,
                    end($words)->id ?? 0,
                    $missingWordIds,
                    $missingWords
                );
            }

            // Dynamic timings resolved relative to trimmed audio bounds
            $startMs = $words[0]->start_ms_from_surah ?? 0;
            $endMs = end($words)->end_ms_from_surah ?? 0;

            // Enforce: endMs >= startMs
            if ($endMs < $startMs) {
                // Log NEGATIVE DURATION PREVENTED
                \Illuminate\Support\Facades\Log::warning('NEGATIVE DURATION PREVENTED', [
                    'segment_order' => $range['segmentOrder'],
                    'original_start' => $startMs,
                    'original_end' => $endMs,
                ]);
                $endMs = $startMs;
            }

            $segments[] = new Segment(
                $range['segmentOrder'],
                $range['startWordId'],
                $range['endWordId'],
                $range['wordIds'],
                $startMs,
                $endMs,
                $words
            );
        }

        return $segments;
    }
}
