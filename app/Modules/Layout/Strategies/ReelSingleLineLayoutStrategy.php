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
            'width' => 1080,
            'height' => 1920,
            'fontSize' => $fontSize,
            'maxVisibleLines' => 1,
            'reciterNameArabic' => $reciterNameArabic,
            'renderUnits' => [
                [
                    'words' => $segment->words,
                    'y' => 960,
                ]
            ],
            'translationBounds' => $this->getTranslationBounds(),
        ];
    }

    public function getTranslationBounds(): array
    {
        return [
            'y' => 1180,              // Center Y coordinate of translation block (below Arabic at 960)
            'width' => 800,           // More focused wrapping width to look publication-style and elegant
            'margin' => 140,          // Safe margin bounds (centered inside 1080px canvas)
            'fontSize' => 30,         // Georgia serif font has a larger visual footprint; 30px is very refined
            'lineHeight' => 1.45,     // Elegant line height spacing for serif typography
            'fontPath' => base_path('fonts/georgia.ttf'), // Publication-grade Georgia font for English prose
            'color' => [200, 200, 200], // Muted off-white/light-gray to establish secondary visual hierarchy
        ];
    }
}
