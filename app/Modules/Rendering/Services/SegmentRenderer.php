<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Layout\Services\FontResolver;
use App\Modules\Quran\Models\Surah;
use App\Modules\Segmentation\DTO\Segment;
use App\Modules\Shared\Services\QuranPathResolver;
use ArPHP\I18N\Arabic;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SegmentRenderer
{
    protected FontResolver $fontResolver;
    protected QuranPathResolver $pathResolver;

    public function __construct(
        FontResolver $fontResolver,
        QuranPathResolver $pathResolver
    ) {
        $this->fontResolver = $fontResolver;
        $this->pathResolver = $pathResolver;
    }

    /**
     * Render a single Segment object as a vertical reel PNG using the layout's render model.
     *
     * @param Surah $surah
     * @param Segment $segment
     * @param string $outputPath
     * @param array $layoutData
     * @return void
     */
    public function renderSegment(Surah $surah, Segment $segment, string $outputPath, array $layoutData): void
    {
        Log::info("[SegmentRenderer] Starting segment render using layout strategy render model", [
            'surah' => $surah->number,
            'segment_index' => $segment->index,
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

        $rawReciterText = $layoutData['reciterNameArabic'] ?? '';
        $reciterText = '';
        if ($rawReciterText !== '') {
            $arabic = new Arabic('Glyphs');
            $reciterText = $arabic->utf8Glyphs($rawReciterText);
        }

        if ($reciterText !== '') {
            $reciterSize = 20;
            $reciterY = 320;

            $bbox = imagettfbbox($reciterSize, 0, $cairoFont, $reciterText);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $reciterX = (int) (($width - $textWidth) / 2);

            imagettftext($im, $reciterSize, 0, $reciterX, $reciterY, $white, $cairoFont, $reciterText);
        }

        // 5. Draw Quran text units from Render Model
        $fontSize = $layoutData['fontSize'] ?? 56;
        $renderUnits = $layoutData['renderUnits'] ?? [];

        // Center rendering of a single visible Arabic line model
        if (!empty($renderUnits)) {
            $unit = $renderUnits[0];
            $words = $unit['words'] ?? [];

            if (!empty($words)) {
                $firstWord = $words[0];
                $page = $firstWord->page_number;
                $fontPath = $this->fontResolver->resolve($page);

                // Compile glyph text using GlyphStringCompiler
                $lineText = \App\Modules\Shared\Utils\GlyphStringCompiler::compile($words);

                $centerY = 960; // Centered vertically for mobile reel layout

                // Compute center position
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $lineText);
                $textWidth = abs($bbox[4] - $bbox[0]);
                $lineX = (int) (($width - $textWidth) / 2);

                imagettftext($im, $fontSize, 0, $lineX, $centerY, $white, $fontPath, $lineText);
            }
        }

        // 6. Save the segment frame to PNG
        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        imagepng($im, $outputPath);
        imagedestroy($im);

        Log::info("[SegmentRenderer] Saved segment frame to {$outputPath}");
    }
}
