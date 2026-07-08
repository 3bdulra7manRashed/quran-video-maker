<?php

namespace App\Modules\Rendering\Services;

use Illuminate\Support\Facades\Log;

class SegmentTimelineResolver
{
    /**
     * Convert Word Timings and raw parameters into a SegmentTimeline.
     *
     * @param array $alignedRanges Aligned segment ranges.
     * @param int|null $totalAudioDurationMs
     * @return SegmentTimeline
     */
    public function resolve(array $alignedRanges, ?int $totalAudioDurationMs = null): SegmentTimeline
    {
        $timeline = [];
        $tempSegments = [];

        // 1. Calculate timing bounds based on available word timings
        foreach ($alignedRanges as $range) {
            $words = $range['words'];

            // Log missing timings warning
            $missingWordIds = [];
            $missingWords = [];
            foreach ($words as $w) {
                if ($w->char_type === 'word' && ($w->start_ms_from_surah === null || $w->end_ms_from_surah === null)) {
                    $missingWordIds[] = $w->id;
                    $missingWords[] = $w->uthmani_text;
                }
            }

            if (!empty($missingWordIds)) {
                Log::warning('MISSING TIMINGS', [
                    'segment_order' => $range['segmentOrder'],
                    'start_index' => $words[0]->id ?? null,
                    'end_index' => end($words)->id ?? null,
                    'missing_word_ids' => $missingWordIds,
                    'missing_words' => $missingWords,
                ]);
            }

            $segStartMs = null;
            $segEndMs = null;
            foreach ($words as $w) {
                if ($w->char_type === 'word' && $w->start_ms_from_surah !== null && $w->end_ms_from_surah !== null) {
                    if ($segStartMs === null || $w->start_ms_from_surah < $segStartMs) {
                        $segStartMs = $w->start_ms_from_surah;
                    }
                    if ($segEndMs === null || $w->end_ms_from_surah > $segEndMs) {
                        $segEndMs = $w->end_ms_from_surah;
                    }
                }
            }

            $startMs = $segStartMs ?? 0;
            $endMs = $segEndMs ?? 0;

            if ($endMs < $startMs) {
                Log::warning('NEGATIVE DURATION PREVENTED', [
                    'segment_order' => $range['segmentOrder'],
                    'original_start' => $startMs,
                    'original_end' => $endMs,
                ]);
                $endMs = $startMs;
            }

            $tempSegments[] = (object) [
                'index' => $range['segmentOrder'],
                'startMs' => $startMs,
                'endMs' => $endMs,
                'words' => $words,
            ];
        }

        $segmentCount = count($tempSegments);
        if ($segmentCount > 0) {
            // 2. Identify and interpolate contiguous clusters of missing timing segments (startMs === 0 && endMs === 0)
            $i = 0;
            while ($i < $segmentCount) {
                if ($tempSegments[$i]->startMs === 0 && $tempSegments[$i]->endMs === 0) {
                    $cluster = [];
                    $startIdx = $i;
                    while ($i < $segmentCount && $tempSegments[$i]->startMs === 0 && $tempSegments[$i]->endMs === 0) {
                        $cluster[] = $tempSegments[$i];
                        $i++;
                    }
                    $endIdx = $i - 1;
                    
                    $leftIdx = $startIdx - 1;
                    $anchorStart = 0;
                    if ($leftIdx >= 0) {
                        $anchorStart = $tempSegments[$leftIdx]->endMs;
                    }
                    
                    $rightIdx = $endIdx + 1;
                    $anchorEnd = null;
                    if ($rightIdx < $segmentCount) {
                        $anchorEnd = $tempSegments[$rightIdx]->startMs;
                    }
                    
                    if ($anchorEnd === null) {
                        $anchorEnd = $totalAudioDurationMs !== null ? $totalAudioDurationMs : ($leftIdx >= 0 ? $tempSegments[$leftIdx]->endMs + 5000 : 5000);
                    }
                    
                    $N = count($cluster);
                    $segWordCounts = [];
                    $totalWords = 0;
                    foreach ($cluster as $seg) {
                        $cnt = 0;
                        foreach ($seg->words as $w) {
                            if ($w->char_type === 'word') {
                                $cnt++;
                            }
                        }
                        if ($cnt === 0) {
                            $cnt = count($seg->words) ?: 1;
                        }
                        $segWordCounts[$seg->index] = $cnt;
                        $totalWords += $cnt;
                    }
                    
                    $availableDuration = $anchorEnd - $anchorStart;
                    $currentStart = $anchorStart;
                    
                    foreach ($cluster as $idxInCluster => $seg) {
                        $duration = 34; // hard minimum of 34ms
                        if ($availableDuration > $N * 34) {
                            $duration = 34 + ($availableDuration - $N * 34) * ($segWordCounts[$seg->index] / $totalWords);
                        }
                        $duration = (int) round($duration);
                        
                        $seg->startMs = (int) round($currentStart);
                        if ($idxInCluster === $N - 1) {
                            $seg->endMs = (int) $anchorEnd;
                        } else {
                            $seg->endMs = (int) round($currentStart + $duration);
                        }
                        
                        $currentStart = $seg->endMs;
                    }
                } else {
                    $i++;
                }
            }

            // 3. Apply contiguous boundary adjustment (gap prevention) strategy
            $tempSegments[0]->startMs = 0;

            for ($i = 0; $i < $segmentCount - 1; $i++) {
                $curr = $tempSegments[$i];
                $next = $tempSegments[$i + 1];

                $curr->endMs = $next->startMs;

                if ($curr->endMs <= $curr->startMs) {
                    $curr->endMs = $curr->startMs + 1;
                    $next->startMs = $curr->endMs;
                }
            }

            $last = $tempSegments[$segmentCount - 1];
            if ($totalAudioDurationMs !== null) {
                $last->endMs = max($last->startMs + 1, $totalAudioDurationMs);
            } else {
                $last->endMs = max($last->startMs + 1, $last->endMs);
            }

            // 4. Fill final timeline map
            foreach ($tempSegments as $seg) {
                $timeline[$seg->index] = [
                    'start_ms' => $seg->startMs,
                    'end_ms' => $seg->endMs,
                ];
            }
        }

        return new SegmentTimeline($timeline);
    }
}
