<?php

namespace App\Modules\Layout\Strategies;

use App\Modules\Segmentation\DTO\Segment;

class ReelSingleLineLayoutStrategy
{
    /**
     * Map a Segment to a single visible Arabic line render model using config-driven values.
     *
     * @param Segment $segment
     * @return array
     */
    public function layout(Segment $segment): array
    {
        $fontSize = config('layouts.reels.font_size', 56);

        return [
            'fontSize' => $fontSize,
            'maxVisibleLines' => 1,
            'renderUnits' => [
                [
                    'words' => $segment->words,
                ]
            ]
        ];
    }
}
