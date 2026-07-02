<?php

namespace App\Services\ContentGeneration;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GeneratedContentWordAligner
{
    /**
     * Align Quran words to approved segment boundaries.
     *
     * @param Collection $approvedSegments Collection of ReelsGeneratedContent models.
     * @param array $words Sorted array of Word models from the database.
     * @return array Array of aligned word ranges.
     */
    public function align(Collection $approvedSegments, array $words): array
    {
        $alignedRanges = [];
        $currentIndex = 0;
        $wordCount = count($words);

        foreach ($approvedSegments as $genSeg) {
            $segmentText = $genSeg->arabic;

            // Normalize segment text for matching
            $normSegment = ContentNormalizer::normalizeForMatching($segmentText);
            $normSegmentClean = preg_replace('/\s+/u', '', $normSegment);

            $matchedWords = [];
            $currentMatch = "";
            $startIndex = $currentIndex;

            // Log ALIGNMENT ATTEMPT
            Log::info('ALIGNMENT ATTEMPT', [
                'segment_order' => (int)$genSeg->segment_order,
                'segment_text' => $segmentText,
                'normalized_segment' => $normSegmentClean,
                'start_index' => $startIndex,
            ]);

            while ($currentIndex < $wordCount) {
                $word = $words[$currentIndex];
                $matchedWords[] = $word;

                $normTimingWord = ContentNormalizer::normalizeForMatching($word->uthmani_text);
                $currentMatch .= preg_replace('/\s+/u', '', $normTimingWord);

                $currentIndex++;

                if ($currentMatch === $normSegmentClean) {
                    break;
                }

                // Safety check to prevent running away if matching goes out of sync (early failure)
                if (mb_strlen($currentMatch) > mb_strlen($normSegmentClean) + 3) {
                    // Log ALIGNMENT FAILURE
                    Log::error('Alignment Failure (Runaway Stopped Early)', [
                        'segment_order' => (int)$genSeg->segment_order,
                        'segment_original' => $segmentText,
                        'segment_normalized' => $normSegmentClean,
                        'candidate_original' => collect($matchedWords)->pluck('uthmani_text')->implode(' '),
                        'candidate_normalized' => $currentMatch,
                        'segment_length' => mb_strlen($normSegmentClean),
                        'candidate_length' => mb_strlen($currentMatch),
                        'segment_hex' => bin2hex($normSegmentClean),
                        'candidate_hex' => bin2hex($currentMatch),
                        'candidate_word_count' => count($matchedWords),
                        'candidate_words' => collect($matchedWords)->pluck('uthmani_text')->toArray(),
                    ]);

                    Log::error('Alignment Cursor State (Runaway)', [
                        'current_index' => $currentIndex,
                        'start_index' => $startIndex,
                        'end_index' => $currentIndex - 1,
                        'normalized_segment_words' => explode(' ', $normSegmentClean),
                        'normalized_candidate_words' => explode(' ', $currentMatch),
                    ]);

                    throw new \App\Services\ContentGeneration\Exceptions\AlignmentException(
                        "Alignment failed for segment #{$genSeg->segment_order} (runaway accumulation stopped early). Mismatch between pre-generated Arabic text and database words.",
                        (int)$genSeg->segment_order,
                        $segmentText,
                        $normSegmentClean,
                        $matchedWords,
                        $startIndex,
                        $currentIndex
                    );
                }
            }

            // Verify: exact match validation
            if ($currentMatch === $normSegmentClean && !empty($matchedWords)) {
                // Log ALIGNMENT SUCCESS
                Log::info('ALIGNMENT SUCCESS', [
                    'segment_order' => (int)$genSeg->segment_order,
                    'matched_words_count' => count($matchedWords),
                ]);

                $wordIds = array_map(fn($w) => $w->id, $matchedWords);

                $alignedRanges[] = [
                    'segmentOrder' => (int)$genSeg->segment_order,
                    'startWordId' => $matchedWords[0]->id,
                    'endWordId' => end($matchedWords)->id,
                    'wordIds' => $wordIds,
                    'words' => $matchedWords,
                ];
            } else {
                // Log ALIGNMENT FAILURE
                Log::error('Alignment Failure', [
                    'segment_order' => (int)$genSeg->segment_order,
                    'segment_original' => $segmentText,
                    'segment_normalized' => $normSegmentClean,
                    'candidate_original' => collect($matchedWords)->pluck('uthmani_text')->implode(' '),
                    'candidate_normalized' => $currentMatch,
                    'segment_length' => strlen($normSegmentClean),
                    'candidate_length' => strlen($currentMatch),
                    'segment_hex' => bin2hex($normSegmentClean),
                    'candidate_hex' => bin2hex($currentMatch),
                    'candidate_word_count' => count($matchedWords),
                    'candidate_words' => collect($matchedWords)->pluck('uthmani_text')->toArray(),
                ]);

                Log::error('Alignment Cursor State', [
                    'current_index' => $currentIndex,
                    'start_index' => $startIndex,
                    'end_index' => $currentIndex - 1,
                    'normalized_segment_words' => explode(' ', $normSegmentClean),
                    'normalized_candidate_words' => explode(' ', $currentMatch),
                ]);

                throw new \App\Services\ContentGeneration\Exceptions\AlignmentException(
                    "Alignment failed for segment #{$genSeg->segment_order}. Mismatch between pre-generated Arabic text and database words.",
                    (int)$genSeg->segment_order,
                    $segmentText,
                    $normSegmentClean,
                    $matchedWords,
                    $startIndex,
                    $currentIndex
                );
            }
        }

        return $alignedRanges;
    }
}
