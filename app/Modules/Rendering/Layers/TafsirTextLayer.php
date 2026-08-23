<?php

namespace App\Modules\Rendering\Layers;

use App\Modules\Rendering\Contracts\RenderLayerInterface;
use App\Modules\Rendering\Domain\FrameContext;
use Illuminate\Support\Facades\Log;

class TafsirTextLayer implements RenderLayerInterface
{
    public const DEFAULT_WIDTH = 760;
    public const DEFAULT_FONT_SIZE = 28;
    public const MAX_TAFSIR_LINES = 3;
    public const MIN_FONT_SIZE = 24;

    protected array $tafsirSegments;
    protected array $widthCache = [];

    /**
     * @param \App\Modules\Rendering\Domain\TranslationSegment[] $tafsirSegments
     */
    public function __construct(array $tafsirSegments = [])
    {
        $this->tafsirSegments = $tafsirSegments;
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
     * Measure wrapped Tafsir layout.
     */
    public function measureTafsirLayout(
        string $text,
        float $fontSize,
        string $fontPath,
        float $width,
        float $lineHeightMult = 1.5
    ): array {
        $lines = $this->wrapText($text, $fontSize, $fontPath, $width);
        $height = count($lines) * $fontSize * $lineHeightMult;
        return [
            'lines' => $lines,
            'wrappedText' => implode("\n", $lines),
            'height' => $height,
            'fontSize' => $fontSize,
            'width' => $width,
        ];
    }

    /**
     * Render the Tafsir layer on the canvas.
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
        $bounds = $context->layoutData['tafsirBounds'] ?? null;
        if (!$bounds) {
            // Default fallback bounds for Tafsir if strategy doesn't define it explicitly
            $bounds = [
                'y' => ($context->height === 1920) ? 1250 : 880,
                'width' => ($context->height === 1920) ? 760 : 1300,
                'fontSize' => 28,
                'lineHeight' => 1.5,
                'fontPath' => base_path('fonts/Cairo-Regular.ttf'),
                'color' => [235, 235, 200],
            ];
        }

        // 2. Find active Tafsir segment by timing match if not supplied directly
        if ($activeSegment === null) {
            $activeSegment = $this->getSegmentForArabic($context->arabicSegment);
        }

        if (!$activeSegment || empty(trim($activeSegment->text))) {
            return;
        }

        // 3. Extract rendering properties
        $fontPath = $bounds['fontPath'] ?? base_path('fonts/Cairo-Regular.ttf');
        $fontSize = $bounds['fontSize'] ?? 28;
        $lineHeightMult = $bounds['lineHeight'] ?? 1.5;
        $maxWidth = $bounds['width'] ?? 760;
        $centerY = $bounds['y'] ?? 1250;
        $colorRGB = $bounds['color'] ?? [235, 235, 200];

        if (!file_exists($fontPath)) {
            $fontPath = base_path('fonts/Cairo-Regular.ttf');
            if (!file_exists($fontPath)) {
                return;
            }
        }

        // 4. Measure layout and wrap text into balanced lines
        $layoutResult = $this->measureTafsirLayout($activeSegment->text, $fontSize, $fontPath, $maxWidth, $lineHeightMult);
        $lines = $layoutResult['lines'];

        if (empty($lines)) {
            return;
        }

        $totalHeight = $layoutResult['height'];
        $startY = $centerY - ($totalHeight / 2) + $fontSize;

        // Allocate color
        $color = imagecolorallocate($context->image, $colorRGB[0], $colorRGB[1], $colorRGB[2]);
        if ($color === false) {
            $color = imagecolorallocate($context->image, 235, 235, 200);
        }

        foreach ($lines as $idx => $line) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $w = abs($bbox[4] - $bbox[0]);
            $lineX = (int) (($context->width - $w) / 2);
            $lineY = (int) ($startY + ($idx * $fontSize * $lineHeightMult));

            imagettftext($context->image, $fontSize, 0, $lineX, $lineY, $color, $fontPath, $line);
        }
    }

    /**
     * Wrap text into lines using balanced partitioning.
     */
    protected function wrapText(string $text, float $fontSize, string $fontPath, float $maxWidth): array
    {
        $words = explode(' ', $text);
        if (empty($words)) {
            return [];
        }

        $greedyLines = [];
        $currentLine = '';
        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $testLine);
            $width = abs($bbox[4] - $bbox[0]);

            if ($width <= $maxWidth) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $greedyLines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }
        if ($currentLine !== '') {
            $greedyLines[] = $currentLine;
        }

        $linesCount = count($greedyLines);
        if ($linesCount <= 1) {
            return $greedyLines;
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
            return $greedyLines;
        }

        $balancedLines = [];
        $start = 0;
        foreach ($result['splits'] as $splitIndex) {
            $slice = array_slice($words, $start, $splitIndex - $start + 1);
            $balancedLines[] = implode(' ', $slice);
            $start = $splitIndex + 1;
        }
        $slice = array_slice($words, $start);
        $balancedLines[] = implode(' ', $slice);

        return $balancedLines;
    }

    protected function getSliceWidth(array $words, int $start, int $end, float $fontSize, string $fontPath): float
    {
        $key = $start . '_' . $end;
        if (isset($this->widthCache[$key])) {
            return $this->widthCache[$key];
        }

        $slice = array_slice($words, $start, $end - $start + 1);
        $text = implode(' ', $slice);
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
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
