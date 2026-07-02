<?php

namespace App\Services\ContentGeneration;

use App\Modules\Rendering\Domain\TranslationSegment;
use Illuminate\Support\Collection;

class ApprovedTranslationSegmentBuilder
{
    /**
     * Build TranslationSegment DTOs from approved segments and finalized Arabic Segment DTOs.
     *
     * @param Collection $approvedSegments Collection of ReelsGeneratedContent models.
     * @param \App\Modules\Segmentation\DTO\Segment[] $segments
     * @param int $surahNumber
     * @return TranslationSegment[]
     */
    public function build(Collection $approvedSegments, array $segments, int $surahNumber): array
    {
        $translationSegments = [];

        foreach ($segments as $segment) {
            $genSeg = $approvedSegments->firstWhere('segment_order', $segment->index);
            if (!$genSeg) {
                continue;
            }

            $words = $segment->words;
            $ayahNumbers = array_unique(array_map(function ($w) {
                return $w->ayah ? $w->ayah->ayah_number : 1;
            }, $words));

            $fromAyah = min($ayahNumbers);
            $toAyah = max($ayahNumbers);

            $textClean = ContentNormalizer::normalizeTranslation($genSeg->translation);

            $translationSegments[] = new TranslationSegment(
                $surahNumber,
                $textClean,
                $fromAyah,
                $toAyah,
                $segment->startMs,
                $segment->endMs
            );
        }

        return $translationSegments;
    }
}
