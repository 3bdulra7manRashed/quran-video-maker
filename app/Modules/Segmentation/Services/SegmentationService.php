<?php

namespace App\Modules\Segmentation\Services;

use App\Modules\Segmentation\Constraints\SegmentConstraint;
use App\Modules\Segmentation\DTO\Segment;
use Illuminate\Support\Facades\Log;

class SegmentationService
{
    protected SegmentConstraint $constraint;

    public function __construct(SegmentConstraint $constraint)
    {
        $this->constraint = $constraint;
    }

    /**
     * Segment an ordered word stream of a Surah into Segment DTOs using deterministic rules and constraints.
     *
     * @param array $words Sorted array/collection of Word models.
     * @param float $totalAudioDurationSec
     * @return Segment[]
     */
    public function segment(array $words, float $totalAudioDurationSec): array
    {
        Log::info("[SegmentationService] Starting segmentation with SegmentConstraint", [
            'words_count' => count($words),
            'audio_duration' => $totalAudioDurationSec,
        ]);

        if (empty($words)) {
            return [];
        }

        $totalAudioDurationMs = (int) round($totalAudioDurationSec * 1000);
        $wordCount = count($words);
        $segments = [];
        $segmentIndex = 1;

        $currentIndex = 0;
        while ($currentIndex < $wordCount) {
            $segmentWords = [];
            $startMs = null;
            $lastSpokenEndMs = null;

            $closed = false;
            $splitAtIndex = null;

            for ($j = $currentIndex; $j < $wordCount; $j++) {
                $w = $words[$j];
                
                // Construct candidate words array (collected words + current word)
                $candidateWords = array_slice($words, $currentIndex, $j - $currentIndex + 1);

                // 1. Validation constraint check (exceeds width limit)
                // This has the absolute highest priority! If adding the current word violates the constraint, we backtrack immediately.
                if (!$this->constraint->accepts($candidateWords)) {
                    $backtracked = false;

                    // Backtrack to search for a boundary (verse end, pause, or line transition)
                    for ($k = count($segmentWords) - 1; $k >= 0; $k--) {
                        $wordK = $segmentWords[$k];
                        $streamIndexK = $currentIndex + $k;

                        $isMarker = ($wordK->char_type === 'end' || $wordK->char_type === 'pause');
                        $isLineBoundary = false;
                        if ($streamIndexK + 1 < $wordCount) {
                            $nextWordK = $words[$streamIndexK + 1];
                            if ($nextWordK->line_number !== $wordK->line_number || $nextWordK->page_number !== $wordK->page_number) {
                                $isLineBoundary = true;
                            }
                        }

                        if ($isMarker || $isLineBoundary) {
                            $splitAtIndex = $streamIndexK;
                            $backtracked = true;
                            break;
                        }
                    }

                    if (!$backtracked) {
                        // Fallback: split at previous word
                        $splitAtIndex = $j - 1;
                    }

                    $closed = true;
                    break;
                }

                // If valid, add it to our segment words array
                $segmentWords[] = $w;

                // Update timings for spoken words
                if ($w->char_type === 'word') {
                    if ($startMs === null) {
                        $startMs = $w->start_ms_from_surah;
                    }
                    $lastSpokenEndMs = $w->end_ms_from_surah;
                }

                // Compute candidate segment duration
                $durationSec = 0.0;
                if ($startMs !== null && $lastSpokenEndMs !== null) {
                    $durationSec = ($lastSpokenEndMs - $startMs) / 1000.0;
                }

                // Last word in stream check
                if ($j === $wordCount - 1) {
                    $splitAtIndex = $j;
                    $closed = true;
                    break;
                }

                // Collect while duration is below 8 seconds
                if ($durationSec < 8.0) {
                    continue;
                }

                // Duration reaches 8 seconds, search for suitable boundary (end or pause marker)
                if ($durationSec >= 8.0 && $durationSec <= 15.0) {
                    if ($w->char_type === 'end' || $w->char_type === 'pause') {
                        $splitAtIndex = $j;
                        $closed = true;
                        break;
                    }
                    continue;
                }

                // Duration reaches 15 seconds, close at nearest valid boundary (marker or line transition)
                if ($durationSec > 15.0 && $durationSec < 20.0) {
                    if ($w->char_type === 'end' || $w->char_type === 'pause') {
                        $splitAtIndex = $j;
                        $closed = true;
                        break;
                    }
                    // Check line boundary
                    $nextWord = $words[$j + 1];
                    if ($nextWord->line_number !== $w->line_number || $nextWord->page_number !== $w->page_number) {
                        $splitAtIndex = $j;
                        $closed = true;
                        break;
                    }
                    continue;
                }

                // Duration reaches 20 seconds, close immediately at nearest line boundary
                if ($durationSec >= 20.0) {
                    $backtracked = false;
                    for ($k = count($segmentWords) - 1; $k >= 0; $k--) {
                        $wordK = $segmentWords[$k];
                        $streamIndexK = $currentIndex + $k;
                        if ($streamIndexK + 1 < $wordCount) {
                            $nextWordK = $words[$streamIndexK + 1];
                            if ($nextWordK->line_number !== $wordK->line_number || $nextWordK->page_number !== $wordK->page_number) {
                                $splitAtIndex = $streamIndexK;
                                $backtracked = true;
                                break;
                            }
                        }
                    }

                    if (!$backtracked) {
                        $splitAtIndex = $j;
                    }

                    $closed = true;
                    break;
                }
            }

            if (!$closed) {
                $splitAtIndex = $wordCount - 1;
            }

            // Extract words for this segment
            $finalWords = array_slice($words, $currentIndex, $splitAtIndex - $currentIndex + 1);

            // Calculate timing bounds
            $segStartMs = null;
            $segEndMs = null;
            foreach ($finalWords as $fw) {
                if ($fw->char_type === 'word' && $fw->start_ms_from_surah !== null && $fw->end_ms_from_surah !== null) {
                    if ($segStartMs === null || $fw->start_ms_from_surah < $segStartMs) {
                        $segStartMs = $fw->start_ms_from_surah;
                    }
                    if ($segEndMs === null || $fw->end_ms_from_surah > $segEndMs) {
                        $segEndMs = $fw->end_ms_from_surah;
                    }
                }
            }

            $segStartMs = $segStartMs ?? 0;
            $segEndMs = $segEndMs ?? 0;
            $wordIds = array_map(fn($fw) => $fw->id, $finalWords);

            $segments[] = new Segment(
                $segmentIndex++,
                $finalWords[0]->id,
                end($finalWords)->id,
                $wordIds,
                $segStartMs,
                $segEndMs,
                $finalWords
            );

            // Advance pointer
            $currentIndex = $splitAtIndex + 1;
        }

        // Continuous boundary adjustment (gap prevention)
        $segmentCount = count($segments);
        if ($segmentCount > 0) {
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
            $last->endMs = max($last->startMs + 1, $totalAudioDurationMs);
            $last->durationMs = $last->endMs - $last->startMs;
        }

        Log::info("[SegmentationService] Successfully completed segmentation", [
            'segments_count' => count($segments),
        ]);

        return $segments;
    }
}
