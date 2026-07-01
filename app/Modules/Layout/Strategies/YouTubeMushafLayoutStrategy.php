<?php

namespace App\Modules\Layout\Strategies;

use App\Modules\Segmentation\DTO\Segment;

class YouTubeMushafLayoutStrategy
{
    /**
     * Map a YouTube Segment containing 1 or more Mushaf lines to the render model.
     *
     * @param Segment $segment
     * @param array $context
     * @return array
     */
    public function layout(Segment $segment, array $context = []): array
    {
        $reciter = $context['reciter'] ?? null;
        $reciterNameArabic = $reciter ? $reciter->name_arabic : '';

        // Group the words in this segment by their page and line composite key to draw them on separate lines
        $grouped = [];
        foreach ($segment->words as $w) {
            $key = $w->page_number . '_' . $w->line_number;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $w;
        }

        $lines = array_values($grouped);
        $linesCount = count($lines);

        // Calculate layout-agnostic vertical line coordinates centered around Y = 580
        $fontSize = 50;
        $lineSpacing = 160; // 160px separation
        $startY = 600 - (($linesCount - 1) * ($lineSpacing / 2));

        $renderUnits = [];
        foreach ($lines as $index => $lineWords) {
            $renderUnits[] = [
                'words' => $lineWords,
                'y' => (int) ($startY + ($index * $lineSpacing)),
            ];
        }

        return [
            'width' => 1920,
            'height' => 1080,
            'fontSize' => $fontSize,
            'header' => [
                'show' => true,
                'surahY' => 150,
                'reciterY' => 230,
                'reciterText' => $reciterNameArabic,
            ],
            'renderUnits' => $renderUnits,
            'translationBounds' => $this->getTranslationBounds(),
        ];
    }

    public function getTranslationBounds(): array
    {
        return [
            'y' => 860,              // Center Y coordinate of translation block (below Arabic)
            'width' => 1200,          // Maximum wrapping width (safe margins on 1920 canvas)
            'margin' => 360,          // Balanced safe margin bounds (centered inside 1920px canvas)
            'fontSize' => 32,         // Elegant Georgia serif size for landscape visual hierarchy
            'lineHeight' => 1.4,      // Comfortable line height spacing for Georgia
            'fontPath' => base_path('fonts/georgia.ttf'), // Publication-grade Georgia font for English prose
            'color' => [225, 225, 225], // Sharp off-white matching Reels visual style
        ];
    }
}
