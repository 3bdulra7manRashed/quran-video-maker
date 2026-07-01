<?php

namespace App\Modules\Rendering\Layers;

use App\Modules\Rendering\Contracts\RenderLayerInterface;
use App\Modules\Rendering\Domain\FrameContext;

class TranslationTextLayer implements RenderLayerInterface
{
    protected array $translationSegments;
    protected array $widthCache = [];

    /**
     * @param \App\Modules\Rendering\Domain\TranslationSegment[] $translationSegments
     */
    public function __construct(array $translationSegments = [])
    {
        $this->translationSegments = $translationSegments;
    }

    /**
     * Helper to find translation segment by timing matching an Arabic segment.
     *
     * @param \App\Modules\Segmentation\DTO\Segment $arabicSegment
     * @return \App\Modules\Rendering\Domain\TranslationSegment|null
     */
    public function getSegmentForArabic(\App\Modules\Segmentation\DTO\Segment $arabicSegment): ?\App\Modules\Rendering\Domain\TranslationSegment
    {
        foreach ($this->translationSegments as $ts) {
            if ($ts->startMs === $arabicSegment->startMs && $ts->endMs === $arabicSegment->endMs) {
                return $ts;
            }
        }
        return null;
    }

    /**
     * Render the translation layer on the canvas.
     *
     * Supports both:
     * - render(FrameContext $context) [backward compatibility]
     * - render(?TranslationSegment $segment, FrameContext $context) [optimized]
     *
     * @param mixed $segmentOrContext
     * @param FrameContext|null $context
     * @return void
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
        $bounds = $context->layoutData['translationBounds'] ?? null;
        if (!$bounds) {
            // Gracefully ignore translation rendering if strategy doesn't define it
            return;
        }

        // 2. Find the active translation segment by timing match if not supplied
        if ($activeSegment === null) {
            $activeSegment = $this->getSegmentForArabic($context->arabicSegment);
        }

        if (!$activeSegment || empty($activeSegment->text)) {
            return;
        }

        // 3. Extract rendering properties from bounds
        $fontPath = $bounds['fontPath'] ?? base_path('fonts/Cairo-Regular.ttf');
        $fontSize = $bounds['fontSize'] ?? 38;
        $lineHeightMult = $bounds['lineHeight'] ?? 1.4;
        $maxWidth = $bounds['width'] ?? 920;
        $centerY = $bounds['y'] ?? 1150;
        $colorRGB = $bounds['color'] ?? [230, 230, 230];

        if (!file_exists($fontPath)) {
            // Defensive check
            $fontPath = base_path('fonts/Cairo-Regular.ttf');
            if (!file_exists($fontPath)) {
                return;
            }
        }

        // 4. Compute wrapped lines
        $lines = $this->wrapText($activeSegment->text, $fontSize, $fontPath, $maxWidth);
        if (empty($lines)) {
            return;
        }

        // 5. Draw lines centered vertically around centerY
        $totalHeight = count($lines) * $fontSize * $lineHeightMult;
        // The first line baseline Y (imagettftext positions text by the baseline)
        $startY = $centerY - ($totalHeight / 2) + $fontSize;

        // Allocate color
        $color = imagecolorallocate($context->image, $colorRGB[0], $colorRGB[1], $colorRGB[2]);
        if ($color === false) {
            $color = imagecolorallocate($context->image, 255, 255, 255); // fallback to white
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
     * Wrap text into lines using a balanced partitioning algorithm.
     */
    protected function wrapText(string $text, float $fontSize, string $fontPath, float $maxWidth): array
    {
        $words = explode(' ', $text);
        if (empty($words)) {
            return [];
        }

        // 1. Run greedy wrap first to get the baseline minimum line count
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

        // 2. Clear width cache and compute optimal partition
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
            return $greedyLines; // Fallback to greedy if no valid partition found
        }

        // Reconstruct the lines from the split indices
        $balancedLines = [];
        $start = 0;
        foreach ($result['splits'] as $splitIndex) {
            $slice = array_slice($words, $start, $splitIndex - $start + 1);
            $balancedLines[] = implode(' ', $slice);
            $start = $splitIndex + 1;
        }
        // Last line
        $slice = array_slice($words, $start);
        $balancedLines[] = implode(' ', $slice);

        return $balancedLines;
    }

    /**
     * Compute and cache the visual width of a word slice.
     */
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

    /**
     * Find the best splits recursively by minimizing squared line length deviation.
     */
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
        // Base case: 1 line remaining
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
