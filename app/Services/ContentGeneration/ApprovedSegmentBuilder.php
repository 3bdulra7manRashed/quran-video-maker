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
            // Dynamic timings resolved relative to trimmed audio bounds
            $startMs = $words[0]->start_ms_from_surah ?? 0;
            $endMs = end($words)->end_ms_from_surah ?? 0;

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
