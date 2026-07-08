<?php

namespace App\Services\ContentGeneration;

use App\Modules\Segmentation\DTO\Segment;
use App\Modules\Rendering\Services\SegmentTimeline;
use App\Modules\Rendering\Services\SegmentTimelineResolver;

class ApprovedSegmentBuilder
{
    /**
     * Build Segment DTOs from aligned ranges and a SegmentTimeline (or total audio duration for backward compatibility).
     *
     * @param array $alignedRanges
     * @param SegmentTimeline|int|null $timelineOrDuration
     * @return Segment[]
     */
    public function build(array $alignedRanges, $timelineOrDuration = null): array
    {
        // Convert to SegmentTimeline internally if not already provided
        if (!($timelineOrDuration instanceof SegmentTimeline)) {
            $resolver = app(SegmentTimelineResolver::class);
            $timeline = $resolver->resolve($alignedRanges, $timelineOrDuration);
        } else {
            $timeline = $timelineOrDuration;
        }

        $segments = [];

        foreach ($alignedRanges as $range) {
            $words = $range['words'];
            $segTimes = $timeline->getTimesForSegment($range['segmentOrder']);

            $startMs = $segTimes ? $segTimes['start_ms'] : 0;
            $endMs = $segTimes ? $segTimes['end_ms'] : 0;

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
