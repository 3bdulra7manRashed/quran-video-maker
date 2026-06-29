<?php

namespace App\Modules\Segmentation\Constraints;

interface SegmentConstraint
{
    /**
     * Check if the given set of words satisfies the layout constraints.
     *
     * @param array $words
     * @return bool
     */
    public function accepts(array $words): bool;
}
