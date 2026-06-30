<?php

namespace App\Modules\Segmentation\Constraints;

class YouTubeMushafConstraint implements SegmentConstraint
{
    /**
     * YouTube Mushaf layout accepts any number of words because segmentation is determined
     * exclusively by the Mushaf line groupings.
     *
     * @param array $words
     * @return bool
     */
    public function accepts(array $words): bool
    {
        return true;
    }
}
