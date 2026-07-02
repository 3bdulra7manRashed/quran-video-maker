<?php

namespace App\Services\ContentGeneration;

use Illuminate\Support\Collection;

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
            $normArabic = ContentNormalizer::cleanForComparison($genSeg->arabic);
            $matchedWords = [];
            $currentMatch = "";

            while ($currentIndex < $wordCount) {
                $word = $words[$currentIndex];
                $matchedWords[] = $word;
                $currentMatch .= ContentNormalizer::cleanForComparison($word->uthmani_text);
                $currentIndex++;

                if ($currentMatch === $normArabic) {
                    break;
                }
            }

            if (empty($matchedWords)) {
                // If matching goes out of sync (should not happen if validated), we fallback
                continue;
            }

            $wordIds = array_map(fn($w) => $w->id, $matchedWords);

            $alignedRanges[] = [
                'segmentOrder' => (int)$genSeg->segment_order,
                'startWordId' => $matchedWords[0]->id,
                'endWordId' => end($matchedWords)->id,
                'wordIds' => $wordIds,
                'words' => $matchedWords,
            ];
        }

        return $alignedRanges;
    }
}
