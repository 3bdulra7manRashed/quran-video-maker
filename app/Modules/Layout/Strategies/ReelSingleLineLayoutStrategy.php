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
            'x' => 540,               // Target Center X coordinate from Premiere Pro
            'y' => 1081,              // Target Center Y coordinate when standalone (matching capsule template)
            'width' => 920,           // Max text width inside 980px capsule (30px padding on each side)
            'fontSize' => 30,         // Al-Jazeera-Arabic GD points (calibrated from 37pt Premiere Pro)
            'lineHeight' => 44,       // Line-height for 2 lines
            'fontPath' => base_path('fonts/Al-Jazeera-Arabic-Regular.ttf'),
            'color' => [255, 255, 255], // Pure White #FFFFFF
            'dynamicStacking' => true, // Stack below translation if translation is present
            'stackMargin' => 40,      // Margin below translation's bottom boundary (tafsirStartY = translationBottomY + 40)
        ];
    }
}
