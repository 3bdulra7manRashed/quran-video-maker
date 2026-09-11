<?php

namespace App\Modules\Rendering\Layers;

use App\Modules\Rendering\Contracts\RenderLayerInterface;
use App\Modules\Rendering\Domain\FrameContext;
use ArPHP\I18N\Arabic;

class FooterLayer implements RenderLayerInterface
{
    public const DEFAULT_COLOR = [77, 49, 38]; // #4D3126
    public const DEFAULT_SURAH_Y = 1260;
    public const DEFAULT_SURAH_SIZE = 45; // ~40-50pt
    public const DEFAULT_RECITER_Y = 1345;
    public const DEFAULT_RECITER_SIZE = 28; // ~26-30pt
    public const DEFAULT_WATERMARK_Y = 1415;
    public const DEFAULT_WATERMARK_SIZE = 24; // ~24pt
    public const DEFAULT_WATERMARK_TEXT = 'equran.me';
    public const DEFAULT_CENTER_X = 540;
    public const DEFAULT_SURAH_OPTICAL_OFFSET_X = -18;

    protected ?Arabic $arabic = null;

    /**
     * Lazy-load ArPHP Arabic Glyphs converter.
     */
    protected function getArabicConverter(): Arabic
    {
        if ($this->arabic === null) {
            $this->arabic = new Arabic('Glyphs');
        }
        return $this->arabic;
    }

    /**
     * Resolve path to QCF_BSML.ttf font file.
     */
    public function resolveBsmlFont(): string
    {
        $candidates = [
            base_path('fonts/QCF_BSML.ttf'),
            public_path('fonts/QCF_BSML.ttf'),
            resource_path('fonts/QCF_BSML.ttf'),
        ];
        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        return base_path('fonts/QCF_BSML.ttf');
    }

    /**
     * Resolve path to primary Arabic text font (Al-Jazeera-Arabic, falling back to Cairo).
     */
    public function resolveArabicFont(): string
    {
        $candidates = [
            base_path('fonts/Al-Jazeera-Arabic-Regular.ttf'),
            base_path('fonts/Al-Jazeera-Arabic.ttf'),
            'C:/Users/Lenovo/AppData/Local/Microsoft/Windows/Fonts/ArbFONTS-Al-Jazeera-Arabic-Regular.ttf',
            base_path('fonts/Cairo-Regular.ttf'),
        ];
        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        return base_path('fonts/Al-Jazeera-Arabic-Regular.ttf');
    }

    /**
     * Resolve path to Georgia font (local asset font, falling back cleanly to system font).
     */
    public function resolveGeorgiaFont(): string
    {
        $candidates = [
            base_path('fonts/georgia.ttf'),
            base_path('fonts/Georgia.ttf'),
            'C:/Windows/Fonts/georgia.ttf',
            'C:/Windows/Fonts/Georgia.ttf',
        ];
        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        return base_path('fonts/georgia.ttf');
    }

    /**
     * Resolve Surah header calligraphy glyph for QCF_BSML.ttf.
     *
     * Unicode layout:
     * - Prefix glyph: U+FB8C ("سُورَةُ")
     * - Surah 1 to 37: U+FB8D to U+FBB1 (0xFB8C + surahNumber)
     * - Surah 38 to 114: U+FBD3 to U+FC1F (0xFBD3 + (surahNumber - 38))
     *
     * In GD left-to-right rendering, string order is [Surah Name] . [سورة]
     * which renders visual right-to-left calligraphy: "سُورَةُ [اسم السورة]"
     */
    public function resolveSurahGlyph(int $surahNumber): ?string
    {
        if ($surahNumber < 1 || $surahNumber > 114) {
            return null;
        }

        // Check precomputed config first
        $configHeaders = config('qcf_surah_headers');
        if (isset($configHeaders[$surahNumber])) {
            return $configHeaders[$surahNumber];
        }

        $surahPrefix = mb_chr(0xFB8C, 'UTF-8');
        if ($surahNumber <= 37) {
            $nameGlyph = mb_chr(0xFB8C + $surahNumber, 'UTF-8');
        } else {
            $nameGlyph = mb_chr(0xFBD3 + ($surahNumber - 38), 'UTF-8');
        }

        return $nameGlyph . $surahPrefix;
    }

    /**
     * Render the equran.me footer layer:
     * 1. Surah Calligraphy (BSML font) at Y = 1200
     * 2. Reciter Name (Arabic font) at Y = 1275
     * 3. Watermark ("equran.me" in Georgia) at Y = 1335
     *
     * All centered at X = 540 in color #4D3126.
     */
    public function render(FrameContext $context): void
    {
        $layoutData = $context->layoutData;
        $footerConfig = $layoutData['footerBounds'] ?? [];
        $show = $footerConfig['show'] ?? (($layoutData['theme'] ?? '') === 'quran_me');

        if (!$show) {
            return;
        }

        $im = $context->image;
        $colorRgb = $footerConfig['color'] ?? self::DEFAULT_COLOR;
        $color = imagecolorallocate($im, $colorRgb[0], $colorRgb[1], $colorRgb[2]);

        $centerX = $footerConfig['x'] ?? self::DEFAULT_CENTER_X;

        // 1. Surah Calligraphy (QCF_BSML.ttf)
        $surahNumber = $layoutData['surahNumber'] ?? ($layoutData['surah']->number ?? null);
        if ($surahNumber) {
            $surahGlyph = $this->resolveSurahGlyph((int) $surahNumber);
            if ($surahGlyph) {
                $bsmlFont = $footerConfig['surah']['fontPath'] ?? $this->resolveBsmlFont();
                $surahSize = $footerConfig['surah']['fontSize'] ?? self::DEFAULT_SURAH_SIZE;
                $surahY = $footerConfig['surah']['y'] ?? self::DEFAULT_SURAH_Y;
                $surahOpticalOffsetX = (int) ($footerConfig['surah']['opticalOffsetX'] ?? self::DEFAULT_SURAH_OPTICAL_OFFSET_X);

                if (file_exists($bsmlFont)) {
                    $bbox = imagettfbbox($surahSize, 0, $bsmlFont, $surahGlyph);
                    $surahWidth = abs($bbox[4] - $bbox[0]);
                    // Optical compensation: shift left by ~18px to counteract the right-heavy font bearing
                    $surahX = (int) (round($centerX - ($surahWidth / 2)) + $surahOpticalOffsetX);
                    imagettftext($im, $surahSize, 0, $surahX, $surahY, $color, $bsmlFont, $surahGlyph);
                }
            }
        }

        // 2. Reciter Name (Al-Jazeera-Arabic-Regular.ttf)
        $rawReciter = $layoutData['reciterNameArabic'] ?? '';
        if (!empty($rawReciter)) {
            $arabicFont = $footerConfig['reciter']['fontPath'] ?? $this->resolveArabicFont();
            $reciterSize = $footerConfig['reciter']['fontSize'] ?? self::DEFAULT_RECITER_SIZE;
            $reciterY = $footerConfig['reciter']['y'] ?? self::DEFAULT_RECITER_Y;

            if (file_exists($arabicFont)) {
                $reciterText = $this->getArabicConverter()->utf8Glyphs($rawReciter);
                $bbox = imagettfbbox($reciterSize, 0, $arabicFont, $reciterText);
                $textWidth = abs($bbox[4] - $bbox[0]);
                $x = (int) ($centerX - ($textWidth / 2));
                imagettftext($im, $reciterSize, 0, $x, $reciterY, $color, $arabicFont, $reciterText);
            }
        }

        // 3. Watermark: "equran.me" (Georgia.ttf)
        $watermarkText = $footerConfig['watermark']['text'] ?? self::DEFAULT_WATERMARK_TEXT;
        if (!empty($watermarkText)) {
            $georgiaFont = $footerConfig['watermark']['fontPath'] ?? $this->resolveGeorgiaFont();
            $watermarkSize = $footerConfig['watermark']['fontSize'] ?? self::DEFAULT_WATERMARK_SIZE;
            $watermarkY = $footerConfig['watermark']['y'] ?? self::DEFAULT_WATERMARK_Y;

            if (file_exists($georgiaFont)) {
                $bbox = imagettfbbox($watermarkSize, 0, $georgiaFont, $watermarkText);
                $textWidth = abs($bbox[4] - $bbox[0]);
                $x = (int) ($centerX - ($textWidth / 2));
                imagettftext($im, $watermarkSize, 0, $x, $watermarkY, $color, $georgiaFont, $watermarkText);
            }
        }
    }
}
