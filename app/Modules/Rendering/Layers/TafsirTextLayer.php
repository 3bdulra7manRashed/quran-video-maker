<?php

namespace App\Modules\Rendering\Layers;

use App\Modules\Rendering\Contracts\RenderLayerInterface;
use App\Modules\Rendering\Domain\FrameContext;
use App\Modules\Rendering\Domain\TranslationSegment;
use ArPHP\I18N\Arabic;
use Illuminate\Support\Facades\Log;

class TafsirTextLayer implements RenderLayerInterface
{
    public const DEFAULT_WIDTH = 920;
    public const DEFAULT_CENTER_X = 540.0;
    public const DEFAULT_CENTER_Y = 1081.0;
    public const DEFAULT_FONT_SIZE = 30; // GD points (approx 28-32pt calibrated from 37pt Premiere Pro)
    public const DEFAULT_LINE_HEIGHT = 44.0; // 42-46px line-height for 2 lines
    public const MAX_TAFSIR_LINES = 2; // approx 1 to 2 lines max
    public const MIN_FONT_SIZE = 24;

    protected array $tafsirSegments;
    protected array $widthCache = [];
    protected ?Arabic $arabic = null;

    /**
     * @param \App\Modules\Rendering\Domain\TranslationSegment[] $tafsirSegments
     */
    public function __construct(array $tafsirSegments = [])
    {
        $this->tafsirSegments = $tafsirSegments;
    }

    /**
     * Helper to retrieve or instantiate ArPHP Arabic Glyphs converter.
     */
    protected function getArabicConverter(): Arabic
    {
        if ($this->arabic === null) {
            $this->arabic = new Arabic('Glyphs');
        }
        return $this->arabic;
    }

    /**
     * Resolve font path prioritizing Al-Jazeera-Arabic, then Amiri, then Cairo.
     */
    public function resolveFontPath(?string $preferredPath = null): string
    {
        $candidates = array_filter([
            $preferredPath,
            base_path('fonts/Al-Jazeera-Arabic-Regular.ttf'),
            base_path('fonts/Al-Jazeera-Arabic.ttf'),
            getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA') . '/Microsoft/Windows/Fonts/ArbFONTS-Al-Jazeera-Arabic-Regular.ttf' : null,
            'C:/Users/Lenovo/AppData/Local/Microsoft/Windows/Fonts/ArbFONTS-Al-Jazeera-Arabic-Regular.ttf',
            base_path('fonts/Amiri-Regular.ttf'),
            getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA') . '/Microsoft/Windows/Fonts/Amiri Bold.ttf' : null,
            'C:/Users/Lenovo/AppData/Local/Microsoft/Windows/Fonts/Amiri Bold.ttf',
            base_path('fonts/Cairo-Regular.ttf'),
        ]);

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return base_path('fonts/Cairo-Regular.ttf');
    }

    /**
     * Helper to find Tafsir segment matching timing of an Arabic segment.
     */
    public function getSegmentForArabic(\App\Modules\Segmentation\DTO\Segment $arabicSegment): ?\App\Modules\Rendering\Domain\TranslationSegment
    {
        foreach ($this->tafsirSegments as $ts) {
            if ($ts->startMs === $arabicSegment->startMs && $ts->endMs === $arabicSegment->endMs) {
                return $ts;
            }
        }
        return null;
    }

    /**
     * Strip Arabic diacritics/tashkeel (Fatha, Damma, Kasra, Tanween, Shadda, Sukun, Superscript Alif)
     * and Tatweel/Kashida while strictly preserving all Hamza variants (أ, إ, آ, ء, ئ, ؤ) as core letters.
     */
    public function stripDiacritics(string $text): string
    {
        // Strictly targets Tashkeel/Harakat (U+064B to U+0652, U+0670) without affecting Hamzas
        $cleaned = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $text);
        return str_replace('ـ', '', $cleaned);
    }

    /**
     * Measure wrapped Tafsir layout.
     */
    public function measureTafsirLayout(
        string $text,
        float $fontSize,
        string $fontPath,
        float $width,
        float $lineHeight = self::DEFAULT_LINE_HEIGHT
    ): array {
        $cleanTafsir = $this->stripDiacritics($text);
        $lines = $this->wrapText($cleanTafsir, $fontSize, $fontPath, $width);
        $height = count($lines) * $lineHeight;
        return [
            'lines' => $lines,
            'wrappedText' => implode("\n", $lines),
            'height' => $height,
            'fontSize' => $fontSize,
            'width' => $width,
            'lineHeight' => $lineHeight,
        ];
    }

    /**
     * Resolve the Tafsir text prioritizing custom segment-level tafsir over bundled Ayah translation text.
     * Also cleans up any raw footnote tags (e.g. <sup foot_note=...>) and HTML tags,
     * and strips diacritics while preserving all Hamzas.
     */
    public function resolveTafsirText($segmentOrContext, ?FrameContext $context = null, ?TranslationSegment $activeSegment = null): ?string
    {
        $candidate = null;

        // 1. Prioritize segment's custom tafsir directly on the passed segment object/array
        if (is_object($segmentOrContext) && isset($segmentOrContext->tafsir) && !empty(trim((string)$segmentOrContext->tafsir))) {
            $candidate = (string) $segmentOrContext->tafsir;
        } elseif (is_array($segmentOrContext) && isset($segmentOrContext['tafsir']) && !empty(trim((string)$segmentOrContext['tafsir']))) {
            $candidate = (string) $segmentOrContext['tafsir'];
        }

        // 2. Check context's arabicSegment (Segment DTO)
        if ($candidate === null && $context && $context->arabicSegment) {
            if (is_object($context->arabicSegment) && isset($context->arabicSegment->tafsir) && !empty(trim((string)$context->arabicSegment->tafsir))) {
                $candidate = (string) $context->arabicSegment->tafsir;
            } elseif (is_array($context->arabicSegment) && isset($context->arabicSegment['tafsir']) && !empty(trim((string)$context->arabicSegment['tafsir']))) {
                $candidate = (string) $context->arabicSegment['tafsir'];
            }
        }

        // 3. Check layout context (layerContext)
        if ($candidate === null && $context && isset($context->layoutData['tafsir']) && !empty(trim((string)$context->layoutData['tafsir']))) {
            $candidate = (string) $context->layoutData['tafsir'];
        }

        // 4. Fallback to activeSegment (TranslationSegment DTO)
        if ($candidate === null && $activeSegment) {
            if (isset($activeSegment->tafsir) && !empty(trim((string)$activeSegment->tafsir))) {
                $candidate = (string) $activeSegment->tafsir;
            } elseif (!empty(trim((string)$activeSegment->text))) {
                $candidate = (string) $activeSegment->text;
            }
        }

        if ($candidate === null) {
            return null;
        }

        // Clean up footnote tags and HTML tags before rendering
        $cleaned = preg_replace('/<sup\b[^>]*>.*?<\/sup>/is', '', $candidate);
        $cleaned = strip_tags($cleaned);
        $cleaned = preg_replace('/\[[^\]]*\]/', '', $cleaned);
        $cleaned = $this->stripDiacritics($cleaned);
        $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned));

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Render the Tafsir layer on the canvas.
     * Renders only the Arabic text directly into the bounding box without any background shapes.
     */
    public function render($segmentOrContext, ?FrameContext $context = null): void
    {
        if ($segmentOrContext instanceof FrameContext) {
            $context = $segmentOrContext;
            $activeSegment = null;
        } else {
            $activeSegment = $segmentOrContext;
        }

        if (!$context || !$context->arabicSegment) {
            return;
        }

        // 1. Resolve layout bounds
        $bounds = $context->layoutData['tafsirBounds'] ?? [];

        // 2. Find active Tafsir segment by timing match if not supplied directly
        if ($activeSegment === null) {
            $activeSegment = $this->getSegmentForArabic($context->arabicSegment);
        }

        // Resolve Tafsir text prioritizing custom segment-level tafsir
        $rawTafsir = $this->resolveTafsirText($segmentOrContext, $context, $activeSegment);
        if ($rawTafsir === null || $rawTafsir === '') {
            return;
        }

        $tafsirText = $this->stripDiacritics($rawTafsir);

        // 3. Extract rendering properties
        $fontPath = $this->resolveFontPath($bounds['fontPath'] ?? null);
        $fontSize = (float) ($bounds['fontSize'] ?? self::DEFAULT_FONT_SIZE);
        $maxWidth = (float) ($bounds['width'] ?? self::DEFAULT_WIDTH);
        $centerX = (float) ($bounds['x'] ?? self::DEFAULT_CENTER_X);
        $centerY = (float) ($bounds['y'] ?? self::DEFAULT_CENTER_Y);
        $colorRGB = [255, 255, 255]; // Tafsir text remains strictly Pure White #FFFFFF

        if (!file_exists($fontPath)) {
            return;
        }

        // Dynamic stacking: check if translation layer is present and compute tafsirStartY
        $translationBottomY = $context->layoutData['translationBottomY'] ?? null;
        if ($translationBottomY === null && isset($context->layoutData['translationBounds'])) {
            $transText = $context->arabicSegment->translation ?? ($context->layoutData['translation'] ?? null);
            if ($transText !== null && trim((string)$transText) !== '') {
                $tBounds = $context->layoutData['translationBounds'];
                $tCenterY = (float) ($tBounds['y'] ?? 942.0);
                $tFontSize = (float) ($tBounds['fontSize'] ?? 27.0);
                $tLineHeight = (float) ($tBounds['lineHeight'] ?? 1.4);
                $translationBottomY = $tCenterY + ($tFontSize * $tLineHeight / 2.0);
            }
        }

        $dynamicStacking = !empty($bounds['dynamicStacking']);
        $stackMargin = (float) ($bounds['stackMargin'] ?? 60.0);

        $tafsirStartY = null;
        if ($dynamicStacking && $translationBottomY !== null && $translationBottomY > 0) {
            $tafsirStartY = (float) ($translationBottomY + $stackMargin);
        }

        // Resolve line height in pixels
        $rawLineHeight = $bounds['lineHeight'] ?? self::DEFAULT_LINE_HEIGHT;
        if ($rawLineHeight < 10) {
            $lineHeight = (float) ($fontSize * $rawLineHeight);
        } else {
            $lineHeight = (float) $rawLineHeight;
        }
        if ($lineHeight < 40 || $lineHeight > 52) {
            $lineHeight = self::DEFAULT_LINE_HEIGHT;
        }

        // 4. Measure layout and wrap text into balanced, Arabic-shaped lines
        // If text exceeds MAX_TAFSIR_LINES at standard size, try reducing font size
        $candidateFontSizes = [$fontSize];
        if ($fontSize >= 30) {
            $candidateFontSizes = array_unique([$fontSize, 28, 26, 24]);
        }

        $lines = [];
        $actualFontSize = $fontSize;
        $actualLineHeight = $lineHeight;

        foreach ($candidateFontSizes as $candSize) {
            $candLineHeight = ($candSize === $fontSize) ? $lineHeight : (float) round($lineHeight * ($candSize / $fontSize));
            $layoutResult = $this->measureTafsirLayout($tafsirText, $candSize, $fontPath, $maxWidth, $candLineHeight);
            $lines = $layoutResult['lines'];
            $actualFontSize = $candSize;
            $actualLineHeight = $candLineHeight;

            if (count($lines) <= self::MAX_TAFSIR_LINES) {
                break;
            }
        }

        $linesCount = count($lines);
        if ($linesCount === 0) {
            return;
        }

        // 5. Measure bounding box for each line
        $bboxes = [];
        $widths = [];
        foreach ($lines as $idx => $shapedLine) {
            $bbox = imagettfbbox($actualFontSize, 0, $fontPath, $shapedLine);
            $bboxes[$idx] = $bbox;
            $widths[$idx] = abs($bbox[4] - $bbox[0]);
        }

        // Allocate Pure White color
        $color = imagecolorallocate($context->image, $colorRGB[0], $colorRGB[1], $colorRGB[2]);
        if ($color === false) {
            $color = imagecolorallocate($context->image, 255, 255, 255);
        }

        // 6. Draw lines with precise horizontal and vertical positioning
        if ($tafsirStartY !== null) {
            // Dynamic stacking: Ensure Tafsir starts strictly below translationBottomY with clean margin (tafsirStartY = translationBottomY + 40)
            $yMin0 = min($bboxes[0][1], $bboxes[0][3], $bboxes[0][5], $bboxes[0][7]);
            $baseline0 = (int) round($tafsirStartY - $yMin0);

            foreach ($lines as $idx => $shapedLine) {
                $lineWidth = $widths[$idx];
                $startX = (int) round($centerX - ($lineWidth / 2.0));
                $lineY = (int) round($baseline0 + ($idx * $actualLineHeight));

                imagettftext($context->image, $actualFontSize, 0, $startX, $lineY, $color, $fontPath, $shapedLine);
            }
        } elseif ($linesCount === 1) {
            // For 1-line text: Center vertically at Y = 1081 (calibrated capsule center)
            $bbox = $bboxes[0];
            $yMin = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
            $yMax = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
            $lineY = (int) round($centerY - ($yMin + $yMax) / 2.0);

            $lineWidth = $widths[0];
            $startX = (int) round($centerX - ($lineWidth / 2.0));

            imagettftext($context->image, $actualFontSize, 0, $startX, $lineY, $color, $fontPath, $lines[0]);
        } else {
            // For 2-line text: Vertically balance around Y = 1081 with appropriate line-height
            $yMin0 = min($bboxes[0][1], $bboxes[0][3], $bboxes[0][5], $bboxes[0][7]);
            $yMaxLast = max($bboxes[$linesCount - 1][1], $bboxes[$linesCount - 1][3], $bboxes[$linesCount - 1][5], $bboxes[$linesCount - 1][7]);

            $baseline0 = (int) round($centerY - ((($linesCount - 1) * $actualLineHeight) + $yMin0 + $yMaxLast) / 2.0);

            foreach ($lines as $idx => $shapedLine) {
                $lineWidth = $widths[$idx];
                $startX = (int) round($centerX - ($lineWidth / 2.0));
                $lineY = (int) round($baseline0 + ($idx * $actualLineHeight));

                imagettftext($context->image, $actualFontSize, 0, $startX, $lineY, $color, $fontPath, $shapedLine);
            }
        }
    }

    /**
     * Wrap text into lines using balanced partitioning with Arabic shaping and BiDi RTL handling.
     */
    protected function wrapText(string $text, float $fontSize, string $fontPath, float $maxWidth): array
    {
        $cleanText = $this->stripDiacritics($text);
        $cleanText = trim(preg_replace('/\s+/u', ' ', $cleanText));
        if ($cleanText === '') {
            return [];
        }

        $words = explode(' ', $cleanText);
        $words = array_values(array_filter($words, fn($w) => $w !== ''));
        if (empty($words)) {
            return [];
        }

        $arabicConv = $this->getArabicConverter();

        $greedyLines = [];
        $currentLogicalWords = [];

        foreach ($words as $word) {
            $testWords = array_merge($currentLogicalWords, [$word]);
            // Pass max_chars = 1000 to prevent premature artificial wrapping at 50 chars
            $testShaped = $arabicConv->utf8Glyphs(implode(' ', $testWords), 1000);

            $bbox = imagettfbbox($fontSize, 0, $fontPath, $testShaped);
            $width = abs($bbox[4] - $bbox[0]);

            if ($width <= $maxWidth) {
                $currentLogicalWords = $candidateWords ?? $testWords;
            } else {
                if (!empty($currentLogicalWords)) {
                    $greedyLines[] = $currentLogicalWords;
                }
                $currentLogicalWords = [$word];
            }
        }
        if (!empty($currentLogicalWords)) {
            $greedyLines[] = $currentLogicalWords;
        }

        $linesCount = count($greedyLines);
        if ($linesCount <= 1) {
            return [
                $arabicConv->utf8Glyphs(implode(' ', $greedyLines[0]), 1000)
            ];
        }

        // Fast balanced partitioning for 2 lines
        if ($linesCount === 2) {
            $totalWords = count($words);
            $bestSplit = -1;
            $bestDiff = INF;

            for ($i = 0; $i < $totalWords - 1; $i++) {
                $slice1 = array_slice($words, 0, $i + 1);
                $slice2 = array_slice($words, $i + 1);

                $w1 = $this->getSliceWidth($words, 0, $i, $fontSize, $fontPath);
                $w2 = $this->getSliceWidth($words, $i + 1, $totalWords - 1, $fontSize, $fontPath);

                if ($w1 <= $maxWidth && $w2 <= $maxWidth) {
                    $diff = abs($w1 - $w2);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestSplit = $i;
                    }
                }
            }

            if ($bestSplit !== -1) {
                $slice1 = array_slice($words, 0, $bestSplit + 1);
                $slice2 = array_slice($words, $bestSplit + 1);
                return [
                    $arabicConv->utf8Glyphs(implode(' ', $slice1), 1000),
                    $arabicConv->utf8Glyphs(implode(' ', $slice2), 1000),
                ];
            }
        }

        $this->widthCache = [];
        $totalWordsCount = count($words);
        
        $totalWidth = $this->getSliceWidth($words, 0, $totalWordsCount - 1, $fontSize, $fontPath);
        $targetLineLength = $totalWidth / $linesCount;

        $result = $this->findBestPartition(
            $words,
            0,
            $totalWordsCount - 1,
            $linesCount,
            $fontSize,
            $fontPath,
            $maxWidth,
            $targetLineLength
        );

        if ($result['penalty'] === INF || empty($result['splits'])) {
            $fallbackLines = [];
            foreach ($greedyLines as $lw) {
                $fallbackLines[] = $arabicConv->utf8Glyphs(implode(' ', $lw), 1000);
            }
            return $fallbackLines;
        }

        $balancedLines = [];
        $start = 0;
        foreach ($result['splits'] as $splitIndex) {
            $slice = array_slice($words, $start, $splitIndex - $start + 1);
            $balancedLines[] = $arabicConv->utf8Glyphs(implode(' ', $slice), 1000);
            $start = $splitIndex + 1;
        }
        $slice = array_slice($words, $start);
        $balancedLines[] = $arabicConv->utf8Glyphs(implode(' ', $slice), 1000);

        return $balancedLines;
    }

    /**
     * Compute and cache the visual width of an Arabic word slice after shaping.
     */
    protected function getSliceWidth(array $words, int $start, int $end, float $fontSize, string $fontPath): float
    {
        $key = $start . '_' . $end . '_' . (int) $fontSize;
        if (isset($this->widthCache[$key])) {
            return $this->widthCache[$key];
        }

        $slice = array_slice($words, $start, $end - $start + 1);
        $logicalText = implode(' ', $slice);
        $shapedText = $this->getArabicConverter()->utf8Glyphs($logicalText, 1000);

        $bbox = imagettfbbox($fontSize, 0, $fontPath, $shapedText);
        $width = abs($bbox[4] - $bbox[0]);
        
        $this->widthCache[$key] = $width;
        return $width;
    }

    protected function findBestPartition(
        array $words,
        int $start,
        int $end,
        int $linesCount,
        float $fontSize,
        string $fontPath,
        float $maxWidth,
        float $targetLineLength
    ): array {
        if ($linesCount === 1) {
            $width = $this->getSliceWidth($words, $start, $end, $fontSize, $fontPath);
            if ($width > $maxWidth) {
                return ['penalty' => INF, 'splits' => []];
            }
            $penalty = pow($width - $targetLineLength, 2);
            return ['penalty' => $penalty, 'splits' => []];
        }

        $bestPenalty = INF;
        $bestSplits = [];
        $minRemainingWords = $linesCount - 1;
        
        for ($i = $start; $i <= $end - $minRemainingWords; $i++) {
            $firstLineWidth = $this->getSliceWidth($words, $start, $i, $fontSize, $fontPath);
            if ($firstLineWidth > $maxWidth) {
                break;
            }
            
            $subResult = $this->findBestPartition(
                $words,
                $i + 1,
                $end,
                $linesCount - 1,
                $fontSize,
                $fontPath,
                $maxWidth,
                $targetLineLength
            );
            
            if ($subResult['penalty'] !== INF) {
                $firstLinePenalty = pow($firstLineWidth - $targetLineLength, 2);
                $totalPenalty = $firstLinePenalty + $subResult['penalty'];
                if ($totalPenalty < $bestPenalty) {
                    $bestPenalty = $totalPenalty;
                    $bestSplits = array_merge([$i], $subResult['splits']);
                }
            }
        }
        
        return ['penalty' => $bestPenalty, 'splits' => $bestSplits];
    }
}
