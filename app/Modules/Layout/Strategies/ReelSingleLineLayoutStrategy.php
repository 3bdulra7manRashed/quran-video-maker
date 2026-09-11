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
        $reciterNameArabic = $reciter ? ($reciter->name_arabic ?? '') : ($context['reciterNameArabic'] ?? '');
        $theme = $context['theme'] ?? 'default';

        $hasTranslation = !empty($segment->translation)
            || !empty($context['with_translation'])
            || !empty($context['translation']);

        $hasTafsir = !empty($segment->tafsir)
            || !empty($context['with_tafsir'])
            || !empty($context['tafsir']);

        // Determine vertical baseline coordinates to maintain comfortable, generous spacing:
        // - Gap between Ayah & Translation: 65px - 75px (prevents collisions with diacritics & descenders)
        // - Gap between Translation & Tafsir: 55px - 65px (clean breathing room above capsule at Y = 1081)
        if ($hasTranslation && $hasTafsir) {
            $ayahY = 815;
            $translationY = 942;
            $tafsirY = 1081;
        } elseif ($hasTranslation) {
            $ayahY = 900;
            $translationY = 1005;
            $tafsirY = 1081;
        } elseif ($hasTafsir) {
            $ayahY = 920;
            $translationY = 942;
            $tafsirY = 1081;
        } else {
            $ayahY = 960;
            $translationY = 942;
            $tafsirY = 1081;
        }

        $ayahColor = ($theme === 'quran_me') ? [77, 49, 38] : [255, 255, 255];
        $translationBounds = $this->getTranslationBounds($translationY);
        if ($theme === 'quran_me') {
            $translationBounds['color'] = [77, 49, 38];
        }

        $tafsirBounds = $this->getTafsirBounds($tafsirY);

        $layoutData = [
            'width' => 1080,
            'height' => 1920,
            'fontSize' => $fontSize,
            'maxVisibleLines' => 1,
            'reciterNameArabic' => $reciterNameArabic,
            'theme' => $theme,
            'ayahColor' => $ayahColor,
            'renderUnits' => [
                [
                    'words' => $segment->words,
                    'y' => $ayahY,
                ]
            ],
            'translationBounds' => $translationBounds,
            'tafsirBounds' => $tafsirBounds,
        ];

        if ($theme === 'quran_me') {
            $layoutData['header'] = ['show' => false];
            $layoutData['footerBounds'] = $this->getFooterBounds();
        }

        return $layoutData;
    }

    public function getTranslationBounds(int $y = 942): array
    {
        return [
            'y' => $y,                // Center Y coordinate of translation block (comfortably between Ayah and Tafsir)
            'width' => 620,           // Reduced container width (focused translation layout)
            'margin' => 230,          // Balanced safe margin bounds (centered inside 1080px canvas)
            'fontSize' => 27,         // Slightly smaller Georgia serif size for visual subordination
            'lineHeight' => 1.4,      // Tighter but comfortable line height spacing for Georgia
            'fontPath' => base_path('fonts/georgia.ttf'), // Publication-grade Georgia font for English prose
            'color' => [225, 225, 225], // Slightly brighter off-white to enhance clarity and sharpness
            'stackMargin' => 70,      // Gap from Ayah bottom when dynamic stacking is used (65px - 75px)
        ];
    }

    public function getTafsirBounds(int $y = 1081): array
    {
        return [
            'x' => 540,               // Target Center X coordinate from Premiere Pro
            'y' => $y,                // Target Center Y coordinate matching capsule template
            'width' => 920,           // Max text width inside 980px capsule (30px padding on each side)
            'fontSize' => 26.5,       // Al-Jazeera-Arabic GD points (reduced by 3.5pt from 30pt for sleeker look)
            'lineHeight' => 40,       // Line-height for 2 lines
            'fontPath' => base_path('fonts/Al-Jazeera-Arabic-Regular.ttf'),
            'color' => [255, 255, 255], // Pure White #FFFFFF
            'dynamicStacking' => false, // Maintain fixed capsule coordinate alignment at Y = 1081
            'stackMargin' => 60,      // Clean margin (55px - 65px) below translation if dynamic stacking is used
        ];
    }

    public function getFooterBounds(): array
    {
        return [
            'show' => true,
            'x' => 540,
            'color' => [77, 49, 38], // #4D3126
            'top' => 1220,
            'bottom' => 1430,
            'surah' => [
                'y' => 1260,
                'fontSize' => 45, // ~40-50pt
                'fontPath' => base_path('fonts/QCF_BSML.ttf'),
                'opticalOffsetX' => -18,
            ],
            'reciter' => [
                'y' => 1345,
                'fontSize' => 28, // ~26-30pt
                'fontPath' => base_path('fonts/Al-Jazeera-Arabic-Regular.ttf'),
            ],
            'watermark' => [
                'text' => 'equran.me',
                'y' => 1415,
                'fontSize' => 24, // ~24pt
                'fontPath' => base_path('fonts/georgia.ttf'),
            ],
        ];
    }
}
