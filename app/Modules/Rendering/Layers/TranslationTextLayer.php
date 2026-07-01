<?php

namespace App\Modules\Rendering\Layers;

use App\Modules\Rendering\Contracts\RenderLayerInterface;
use App\Modules\Rendering\Domain\FrameContext;

class TranslationTextLayer implements RenderLayerInterface
{
    protected array $translationSegments;

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
     * Wrap text into lines according to maximum width.
     */
    protected function wrapText(string $text, float $fontSize, string $fontPath, float $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $testLine);
            $width = abs($bbox[4] - $bbox[0]);

            if ($width <= $maxWidth) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines;
    }
}
