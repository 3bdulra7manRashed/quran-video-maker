<?php

namespace App\Services\ContentGeneration;

use App\Modules\Segmentation\DTO\Segment;

class ApprovedSegmentBuilder
{
    /**
     * Build Segment DTOs from aligned ranges.
     *
     * @param array $alignedRanges
     * @param int|null $totalAudioDurationMs
     * @return Segment[]
     */
    public function build(array $alignedRanges, ?int $totalAudioDurationMs = null): array
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
                // Log MISSING TIMINGS as warning, not exception
                \Illuminate\Support\Facades\Log::warning('MISSING TIMINGS', [
                    'segment_order' => $range['segmentOrder'],
                    'start_index' => $words[0]->id ?? null,
                    'end_index' => end($words)->id ?? null,
                    'missing_word_ids' => $missingWordIds,
                    'missing_words' => $missingWords,
                ]);
            }

            // Calculate timing bounds based on available word timings
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

        $segmentCount = count($segments);
        if ($segmentCount > 0) {
            // 1. Identify and interpolate contiguous clusters of missing timing segments (startMs === 0 && endMs === 0)
            $i = 0;
            while ($i < $segmentCount) {
                if ($segments[$i]->startMs === 0 && $segments[$i]->endMs === 0) {
                    // Start of a missing cluster
                    $cluster = [];
                    $startIdx = $i;
                    while ($i < $segmentCount && $segments[$i]->startMs === 0 && $segments[$i]->endMs === 0) {
                        $cluster[] = $segments[$i];
                        $i++;
                    }
                    $endIdx = $i - 1;
                    
                    // Find AnchorStart from the segment before the cluster
                    $leftIdx = $startIdx - 1;
                    $anchorStart = 0;
                    if ($leftIdx >= 0) {
                        $anchorStart = $segments[$leftIdx]->endMs;
                    }
                    
                    // Find AnchorEnd from the segment after the cluster
                    $rightIdx = $endIdx + 1;
                    $anchorEnd = null;
                    if ($rightIdx < $segmentCount) {
                        $anchorEnd = $segments[$rightIdx]->startMs;
                    }
                    
                    if ($anchorEnd === null) {
                        $anchorEnd = $totalAudioDurationMs !== null ? $totalAudioDurationMs : ($leftIdx >= 0 ? $segments[$leftIdx]->endMs + 5000 : 5000);
                    }
                    
                    // Calculate total spoken words in the cluster for proportional allocation
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
                        $duration = 34; // hard minimum of 34ms (one frame at 30fps)
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
                        $seg->durationMs = $seg->endMs - $seg->startMs;
                        
                        $currentStart = $seg->endMs;
                    }
                } else {
                    $i++;
                }
            }

            // 2. Apply contiguous boundary adjustment (gap prevention) strategy
            $segments[0]->startMs = 0;

            for ($i = 0; $i < $segmentCount - 1; $i++) {
                $curr = $segments[$i];
                $next = $segments[$i + 1];

                $curr->endMs = $next->startMs;
                $curr->durationMs = $curr->endMs - $curr->startMs;

                if ($curr->endMs <= $curr->startMs) {
                    $curr->endMs = $curr->startMs + 1;
                    $next->startMs = $curr->endMs;
                    $curr->durationMs = 1;
                }
            }

            $last = $segments[$segmentCount - 1];
            if ($totalAudioDurationMs !== null) {
                $last->endMs = max($last->startMs + 1, $totalAudioDurationMs);
            } else {
                $last->endMs = max($last->startMs + 1, $last->endMs);
            }
            $last->durationMs = $last->endMs - $last->startMs;
        }

        return $segments;
    }
}
