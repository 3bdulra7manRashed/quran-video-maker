<?php

namespace App\Modules\Layout\Strategies;

use App\Modules\Segmentation\DTO\Segment;

interface LayoutStrategy
{
    /**
     * Map a Segment to a visible layout model.
     *
     * @param Segment $segment
     * @param array $context Context metadata containing 'reciter' object, styling, etc.
     * @return array
     */
    public function layout(Segment $segment, array $context = []): array;
}
