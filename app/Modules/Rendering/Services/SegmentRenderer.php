<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Word;
use App\Modules\Segmentation\DTO\Segment;
use App\Modules\Layout\Services\FontResolver;
use App\Modules\Shared\Services\QuranPathResolver;
use ArPHP\I18N\Arabic;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SegmentRenderer
{
    protected FontResolver $fontResolver;
    protected BackgroundFactory $backgroundFactory;
    protected QuranPathResolver $pathResolver;

    public function __construct(
        FontResolver $fontResolver,
        BackgroundFactory $backgroundFactory,
        QuranPathResolver $pathResolver
    ) {
        $this->fontResolver = $fontResolver;
        $this->backgroundFactory = $backgroundFactory;
        $this->pathResolver = $pathResolver;
    }

    /**
     * Render a single Segment object using the layout's render model.
     *
     * @param Surah $surah
     * @param Segment $segment
     * @param string $outputPath
     * @param array $layoutData
     * @return void
     */
    public function renderSegment(Surah $surah, Segment $segment, string $outputPath, array $layoutData): void
    {
        Log::info("[SegmentRenderer] Starting segment render using layout-agnostic model", [
            'surah' => $surah->number,
            'segment_index' => $segment->index,
        ]);

        // 1. Resolve canvas dimensions
        $width = $layoutData['width'] ?? 1080;
        $height = $layoutData['height'] ?? 1920;
        $im = imagecreatetruecolor($width, $height);

        // 2. Draw background
        $this->backgroundFactory->apply($im);

        // Colors
        $white = imagecolorallocate($im, 255, 255, 255);

        // 3. Draw headers if enabled in layoutData
        $headerConfig = $layoutData['header'] ?? [];
        $showHeaders = $headerConfig['show'] ?? true;

        if ($showHeaders) {
            $headersConfig = config('qcf_surah_headers');
            $headerGlyph = $headersConfig[$surah->number] ?? null;

            if ($headerGlyph) {
                $bsmlFont = base_path('fonts/QCF_BSML.ttf');
                if (!file_exists($bsmlFont)) {
                    throw new RuntimeException("QCF_BSML.ttf font not found: {$bsmlFont}");
                }

                $headerSize = 55;
                $headerY = $headerConfig['surahY'] ?? 220;

                // Center the header
                $bbox = imagettfbbox($headerSize, 0, $bsmlFont, $headerGlyph);
                $textWidth = abs($bbox[4] - $bbox[0]);
                $headerX = (int) (($width - $textWidth) / 2);

                imagettftext($im, $headerSize, 0, $headerX, $headerY, $white, $bsmlFont, $headerGlyph);
            }

            // Draw Reciter Name (Cairo-Regular.ttf)
            $rawReciterText = $headerConfig['reciterText'] ?? ($layoutData['reciterNameArabic'] ?? '');
            if ($rawReciterText !== '') {
                $cairoFont = base_path('fonts/Cairo-Regular.ttf');
                if (!file_exists($cairoFont)) {
                    throw new RuntimeException("Cairo-Regular.ttf font not found: {$cairoFont}");
                }

                $arabic = new Arabic('Glyphs');
                $reciterText = $arabic->utf8Glyphs($rawReciterText);

                $reciterSize = 20;
                $reciterY = $headerConfig['reciterY'] ?? 320;

                $bbox = imagettfbbox($reciterSize, 0, $cairoFont, $reciterText);
                $textWidth = abs($bbox[4] - $bbox[0]);
                $reciterX = (int) (($width - $textWidth) / 2);

                imagettftext($im, $reciterSize, 0, $reciterX, $reciterY, $white, $cairoFont, $reciterText);
            }
        }

        // 4. Draw Quran text lines
        $fontSize = $layoutData['fontSize'] ?? 56;
        $renderUnits = $layoutData['renderUnits'] ?? [];

        foreach ($renderUnits as $unit) {
            $words = $unit['words'] ?? [];

            if (!empty($words)) {
                $firstWord = $words[0];
                $page = $firstWord->page_number;
                $fontPath = $this->fontResolver->resolve($page);

                // Compile glyph text using GlyphStringCompiler
                $lineText = \App\Modules\Shared\Utils\GlyphStringCompiler::compile($words);

                $centerY = $unit['y'] ?? 960;

                // Compute center position
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $lineText);
                $textWidth = abs($bbox[4] - $bbox[0]);
                $lineX = (int) (($width - $textWidth) / 2);

                imagettftext($im, $fontSize, 0, $lineX, $centerY, $white, $fontPath, $lineText);
            }
        }

        // 5. Save the segment frame to PNG
        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        imagepng($im, $outputPath);
        imagedestroy($im);

        Log::info("[SegmentRenderer] Saved segment frame to {$outputPath}");
    }
}
