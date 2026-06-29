<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Layout\Services\FontResolver;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Word;
use ArPHP\I18N\Arabic;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FrameRenderer
{
    protected FontResolver $fontResolver;

    public function __construct(FontResolver $fontResolver)
    {
        $this->fontResolver = $fontResolver;
    }

    /**
     * Render a static PNG frame for a Surah.
     *
     * @param Surah $surah
     * @param string $outputPath Path to save the rendered PNG frame.
     * @return void
     */
    public function render(Surah $surah, string $outputPath): void
    {
        Log::info("[FrameRenderer] Starting frame render", ['surah' => $surah->number]);

        // 1. Create a 1080x1920 true color image
        $width = 1080;
        $height = 1920;
        $im = imagecreatetruecolor($width, $height);

        // 2. Draw background
        $backgroundPath = storage_path('app/backgrounds/default.png');
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

        $rawReciterText = "ياسر الدوسري";
        $arabic = new Arabic('Glyphs');
        $reciterText = $arabic->utf8Glyphs($rawReciterText);
        $reciterSize = 20;
        $reciterY = 320;

        $bbox = imagettfbbox($reciterSize, 0, $cairoFont, $reciterText);
        $textWidth = abs($bbox[4] - $bbox[0]);
        $reciterX = (int) (($width - $textWidth) / 2);

        imagettftext($im, $reciterSize, 0, $reciterX, $reciterY, $white, $cairoFont, $reciterText);

        // 5. Load and group Quran words by [page_number, line_number]
        $ayahIds = $surah->ayahs()->pluck('id');
        $words = Word::whereIn('ayah_id', $ayahIds)
            ->orderBy('page_number')
            ->orderBy('line_number')
            ->orderBy('ayah_id')
            ->orderBy('word_index')
            ->get();

        $lines = [];
        foreach ($words as $word) {
            $page = $word->page_number;
            $line = $word->line_number ?? 0;
            $key = "{$page}_{$line}";

            if (!isset($lines[$key])) {
                $lines[$key] = [
                    'page' => $page,
                    'line' => $line,
                    'words' => [],
                ];
            }
            $lines[$key]['words'][] = $word;
        }

        // 6. Draw lines of Quran text
        $quranFontSize = 26;
        $lineHeight = 65;
        $startY = 480;
        $maxY = 1800;

        $currentY = $startY;

        foreach ($lines as $lineData) {
            if ($currentY + $lineHeight > $maxY) {
                Log::warning("[FrameRenderer] Content overflow, stopping layout rendering", [
                    'surah' => $surah->number,
                    'last_page' => $lineData['page'],
                    'last_line' => $lineData['line'],
                ]);
                break;
            }

            $page = $lineData['page'];
            $fontPath = $this->fontResolver->resolve($page);

            // Extract glyph strings
            $glyphStrings = array_map(function ($w) {
                return $w->glyph_text;
            }, $lineData['words']);

            // Reverse order of words for RTL layout rendering in LTR GD canvas
            $reversedGlyphs = array_reverse($glyphStrings);
            $lineText = implode(' ', $reversedGlyphs);

            // Compute center position
            $bbox = imagettfbbox($quranFontSize, 0, $fontPath, $lineText);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $lineX = (int) (($width - $textWidth) / 2);

            imagettftext($im, $quranFontSize, 0, $lineX, $currentY, $white, $fontPath, $lineText);
            $currentY += $lineHeight;
        }

        // 7. Save the frame to PNG
        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        imagepng($im, $outputPath);
        imagedestroy($im);

        Log::info("[FrameRenderer] Saved frame to {$outputPath}");
    }
}
