<?php

namespace App\Modules\Layout\Strategies;

use App\Modules\Segmentation\DTO\Segment;

class ReelSingleLineLayoutStrategy
{
    /**
     * Map a Segment to a single visible Arabic line render model using config-driven values.
     *
     * @param Segment $segment
     * @param array $context Context metadata containing 'reciter' object, styling, etc.
     * @return array
     */
    public function layout(Segment $segment, array $context = []): array
    {
        $fontSize = config('layouts.reels.font_size', 56);
        $reciter = $context['reciter'] ?? null;
        $reciterNameArabic = $reciter ? $reciter->name_arabic : '';

        return [
            'fontSize' => $fontSize,
            'maxVisibleLines' => 1,
            'reciterNameArabic' => $reciterNameArabic,
            'renderUnits' => [
                [
                    'words' => $segment->words,
                ]
            ]
        ];
    }
}
