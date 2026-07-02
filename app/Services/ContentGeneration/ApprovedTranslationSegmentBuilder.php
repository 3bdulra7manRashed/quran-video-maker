<?php

namespace App\Services\ContentGeneration;

use App\Modules\Rendering\Domain\TranslationSegment;
use Illuminate\Support\Collection;

class ApprovedTranslationSegmentBuilder
{
    /**
     * Build TranslationSegment DTOs from approved segments and aligned ranges.
     *
     * @param Collection $approvedSegments Collection of ReelsGeneratedContent models.
     * @param array $alignedRanges
     * @param int $surahNumber
     * @return TranslationSegment[]
     */
    public function build(Collection $approvedSegments, array $alignedRanges, int $surahNumber): array
    {
        $translationSegments = [];

        foreach ($alignedRanges as $range) {
            $genSeg = $approvedSegments->firstWhere('segment_order', $range['segmentOrder']);
            if (!$genSeg) {
                continue;
            }

            $words = $range['words'];
            $ayahNumbers = array_unique(array_map(function ($w) {
                return $w->ayah ? $w->ayah->ayah_number : 1;
            }, $words));

            $fromAyah = min($ayahNumbers);
            $toAyah = max($ayahNumbers);

            $startMs = $words[0]->start_ms_from_surah ?? 0;
            $endMs = end($words)->end_ms_from_surah ?? 0;

            $textClean = ContentNormalizer::normalizeTranslation($genSeg->translation);

            $translationSegments[] = new TranslationSegment(
                $surahNumber,
                $textClean,
                $fromAyah,
                $toAyah,
                $startMs,
                $endMs
            );
        }

        return $translationSegments;
    }
}
