<?php

namespace App\Modules\Segmentation\Services;

use App\Modules\Segmentation\Constraints\SegmentConstraint;
use App\Modules\Segmentation\DTO\Segment;
use App\Modules\Quran\Models\Word;
use Illuminate\Support\Facades\Log;

class SegmentationService
{
    protected SegmentConstraint $constraint;

    public function __construct(SegmentConstraint $constraint)
    {
        $this->constraint = $constraint;
    }

    /**
     * Determine if a word contains a Quranic pause marker.
     *
     * Primary Strategy: Check if the Uthmani text contains unicode characters representing
     * superscript pause symbols (U+06D6 to U+06DC).
     *
     * Secondary Heuristic Fallback: If no unicode character is found in uthmani_text, inspect
     * if the glyph_text is multi-character (length > 1), which indicates QCF pause marker
     * suffix codepoints (like U+FB71, U+FB7C, U+FB8A) are present. Note: This is a fallback
     * heuristic and does not guarantee that every multi-character glyph is a pause marker.
     *
     * @param Word $word
     * @return bool
     */
    protected function hasPauseMarker(Word $word): bool
    {
        // 1. Primary: Unicode detection on uthmani_text
        $text = $word->uthmani_text ?? '';
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $ord = mb_ord(mb_substr($text, $i, 1));
            if ($ord >= 0x06D6 && $ord <= 0x06DC) {
                return true;
            }
        }

        // 2. Secondary Heuristic Fallback: multi-character glyph inspection
        $glyph = $word->glyph_text ?? '';
        if (mb_strlen($glyph) > 1) {
            return true;
        }

        return false;
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

        $maxAyahs = config('layouts.reels.max_ayahs_per_segment', 1);
        $mandatoryPause = config('layouts.reels.mandatory_pause_boundary', true);

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

                // 1. Width validation check (Priority 3)
                // Highest constraint priority: If adding this word violates the width constraint,
                // we backtrack immediately and split at the previous boundary or previous word.
                if (!$this->constraint->accepts($candidateWords)) {
                    $backtracked = false;

                    // Backtrack to search for a boundary (pause marker or line transition)
                    for ($k = count($segmentWords) - 1; $k >= 0; $k--) {
                        $wordK = $segmentWords[$k];
                        $streamIndexK = $currentIndex + $k;

                        $isMarker = $this->hasPauseMarker($wordK);
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

                // 2. Single Ayah Rule (Priority 1)
                // Close segment immediately at the ayah boundary transition without relying on end-marker columns.
                if ($maxAyahs === 1) {
                    $isLastWord = ($j === $wordCount - 1);
                    if ($isLastWord || $words[$j + 1]->ayah_id !== $w->ayah_id) {
                        $splitAtIndex = $j;
                        $closed = true;
                        break;
                    }
                }

                // 3. Mandatory Pause Boundary (Priority 2)
                // Close segment immediately when we encounter a pause marker.
                if ($mandatoryPause && $this->hasPauseMarker($w)) {
                    $splitAtIndex = $j;
                    $closed = true;
                    break;
                }

                // If valid and we don't close, add it to our segment words array
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

                // 4. Preferred Duration Window (Priority 4)
                // Split at line boundaries if segment duration is between 8s and 15s.
                if ($durationSec >= 8.0 && $durationSec <= 15.0) {
                    $nextWord = $words[$j + 1];
                    if ($nextWord->line_number !== $w->line_number || $nextWord->page_number !== $w->page_number) {
                        $splitAtIndex = $j;
                        $closed = true;
                        break;
                    }
                }

                // 5. Hard Limit (Priority 5)
                // If segment reaches 20s, force closure immediately at the nearest line boundary.
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
