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
            'tafsirBounds' => $this->getTafsirBounds(),
        ];
    }

    public function getTranslationBounds(): array
    {
        return [
            'y' => 1180,              // Center Y coordinate of translation block (below Arabic at 960)
            'width' => 620,           // Reduced container width (focused translation layout)
            'margin' => 230,          // Balanced safe margin bounds (centered inside 1080px canvas)
            'fontSize' => 27,         // Slightly smaller Georgia serif size (down by 3px) for visual subordination
            'lineHeight' => 1.4,      // Tighter but comfortable line height spacing for Georgia
            'fontPath' => base_path('fonts/georgia.ttf'), // Publication-grade Georgia font for English prose
            'color' => [225, 225, 225], // Slightly brighter off-white to enhance clarity and sharpness
        ];
    }

    public function getTafsirBounds(): array
    {
        return [
            'y' => 1340,              // Center Y coordinate of Tafsir block below translation
            'width' => 760,           // Safe margin container width for Arabic Tafsir
            'fontSize' => 26,         // Cairo font size for Arabic commentary
            'lineHeight' => 1.5,      // Line height spacing for Cairo font
            'fontPath' => base_path('fonts/Cairo-Regular.ttf'), // Cairo font for Arabic prose
            'color' => [235, 235, 200], // Soft cream tint to visually distinguish Tafsir
        ];
    }
}
