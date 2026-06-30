<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Layout\DTO\Frame;
use App\Modules\Layout\Services\FontResolver;
use App\Modules\Quran\Models\Surah;
use App\Modules\Shared\Services\QuranPathResolver;
use ArPHP\I18N\Arabic;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FrameRenderer
{
    protected FontResolver $fontResolver;
    protected QuranPathResolver $pathResolver;

    public function __construct(FontResolver $fontResolver, QuranPathResolver $pathResolver)
    {
        $this->fontResolver = $fontResolver;
        $this->pathResolver = $pathResolver;
    }

    /**
     * Render a single Frame object as a PNG.
     *
     * @param Surah $surah
     * @param Frame $frame
     * @param string $outputPath
     * @param string $reciterNameArabic
     * @return void
     */
    public function renderFrame(Surah $surah, Frame $frame, string $outputPath, string $reciterNameArabic): void
    {
        Log::info("[FrameRenderer] Starting frame render", [
            'surah' => $surah->number,
            'frame_index' => $frame->index,
        ]);

        // 1. Create a 1080x1920 true color image
        $width = 1080;
        $height = 1920;
        $im = imagecreatetruecolor($width, $height);

        // 2. Draw background
        $backgroundPath = $this->pathResolver->backgrounds('default.png');
        if (!file_exists($backgroundPath)) {
            throw new RuntimeException("Background image not found: {$backgroundPath}");
        }

        $bg = imagecreatefrompng($backgroundPath);
        imagecopy($im, $bg, 0, 0, 0, 0, $width, $height);
        imagedestroy($bg);

        // Colors
        $white = imagecolorallocate($im, 255, 255, 255);

        // 3. Draw Surah Name Header (QCF_BSML.ttf)
        $headersConfig = config('qcf_surah_headers');
        $headerGlyph = $headersConfig[$surah->number] ?? null;

        if ($headerGlyph) {
            $bsmlFont = base_path('fonts/QCF_BSML.ttf');
            if (!file_exists($bsmlFont)) {
                throw new RuntimeException("QCF_BSML.ttf font not found: {$bsmlFont}");
            }

            $headerSize = 55;
            $headerY = 220;

            // Center the header
            $bbox = imagettfbbox($headerSize, 0, $bsmlFont, $headerGlyph);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $headerX = (int) (($width - $textWidth) / 2);

            imagettftext($im, $headerSize, 0, $headerX, $headerY, $white, $bsmlFont, $headerGlyph);
        }

        // 4. Draw Reciter Name (Cairo-Regular.ttf)
        $cairoFont = base_path('fonts/Cairo-Regular.ttf');
        if (!file_exists($cairoFont)) {
            throw new RuntimeException("Cairo-Regular.ttf font not found: {$cairoFont}");
        }

        $reciterText = '';
        if ($reciterNameArabic !== '') {
            $arabic = new Arabic('Glyphs');
            $reciterText = $arabic->utf8Glyphs($reciterNameArabic);
        }

        if ($reciterText !== '') {
            $reciterSize = 20;
            $reciterY = 320;

            $bbox = imagettfbbox($reciterSize, 0, $cairoFont, $reciterText);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $reciterX = (int) (($width - $textWidth) / 2);

            imagettftext($im, $reciterSize, 0, $reciterX, $reciterY, $white, $cairoFont, $reciterText);
        }

        // 5. Draw Quran lines
        $quranFontSize = 26;
        $lineHeight = 120;
        $startY = 520;

        foreach ($frame->lines as $lineIndex => $line) {
            $words = $line['words'] ?? [];
            if (empty($words)) {
                continue;
            }

            // Resolve page font from the first word's page number
            $firstWord = $words[0];
            $page = $firstWord->page_number;
            $fontPath = $this->fontResolver->resolve($page);

            // Extract glyph strings
            $glyphStrings = array_map(function ($w) {
                return $w->glyph_text;
            }, $words);

            // Reverse order of words for RTL layout rendering in LTR GD canvas
            $reversedGlyphs = array_reverse($glyphStrings);
            $lineText = implode(' ', $reversedGlyphs);

            // Draw line centered horizontally
            $y = $startY + ($lineIndex * $lineHeight);

            // Compute center position
            $bbox = imagettfbbox($quranFontSize, 0, $fontPath, $lineText);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $lineX = (int) (($width - $textWidth) / 2);

            imagettftext($im, $quranFontSize, 0, $lineX, $y, $white, $fontPath, $lineText);
        }

        // 6. Save image to destination path
        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        imagepng($im, $outputPath);
        imagedestroy($im);

        Log::info("[FrameRenderer] Saved frame image to {$outputPath}");
    }
}
