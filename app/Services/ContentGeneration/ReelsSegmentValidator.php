<?php

namespace App\Services\ContentGeneration;

use App\Services\ContentGeneration\Exceptions\AlignmentException;
use Illuminate\Support\Facades\Log;

class ReelsSegmentValidator
{
    /**
     * Validate that no aligned range/segment crosses an ayah boundary.
     *
     * @param array $alignedRanges
     * @return void
     * @throws AlignmentException
     */
    public function validate(array $alignedRanges): void
    {
        foreach ($alignedRanges as $range) {
            $words = $range['words'];
            if (empty($words)) {
                continue;
            }

            $ayahIds = [];
            foreach ($words as $w) {
                if (isset($w->ayah_id)) {
                    $ayahIds[] = $w->ayah_id;
                }
            }
            $ayahIds = array_unique($ayahIds);
            
            // Temporary logs
            Log::info('REELS AYAH BOUNDARY CHECK', [
                'segment_order' => $range['segmentOrder'],
                'ayah_ids' => $ayahIds,
                'words_count' => count($words),
            ]);

            if (count($ayahIds) > 1) {
                // Find start and current (end) indexes for rich debugging
                $startIndex = $words[0]->id ?? 0;
                $currentIndex = end($words)->id ?? 0;
                
                $normalizedText = ContentNormalizer::normalizeForMatching(
                    implode(' ', array_map(fn($w) => $w->uthmani_text, $words))
                );

                Log::error('ALIGNMENT FAILURE', [
                    'reason' => 'Reels segment crossed ayah boundary',
                    'segment_order' => $range['segmentOrder'],
                    'ayah_ids' => $ayahIds,
                ]);

                throw new AlignmentException(
                    "Reels layout regression: segment #{$range['segmentOrder']} crosses ayah boundaries (contains Ayah IDs: " . implode(', ', $ayahIds) . "). Text must stop at ayah markers.",
                    $range['segmentOrder'],
                    implode(' ', array_map(fn($w) => $w->uthmani_text, $words)),
                    $normalizedText,
                    $words,
                    $startIndex,
                    $currentIndex
                );
            }
        }
    }
}
