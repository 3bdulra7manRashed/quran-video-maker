<?php

namespace App\Modules\Rendering\Services;

class SegmentTimeline
{
    /** @var array<int, array{start_ms: int, end_ms: int}> */
    protected array $timeline = [];

    public function __construct(array $timeline)
    {
        $this->timeline = $timeline;
    }

    /**
     * Get start and end ms for a segment order.
     *
     * @param int $segmentOrder
     * @return array{start_ms: int, end_ms: int}|null
     */
    public function getTimesForSegment(int $segmentOrder): ?array
    {
        return $this->timeline[$segmentOrder] ?? null;
    }

    /**
     * Get the full timeline array.
     *
     * @return array<int, array{start_ms: int, end_ms: int}>
     */
    public function getAll(): array
    {
        return $this->timeline;
    }
}
