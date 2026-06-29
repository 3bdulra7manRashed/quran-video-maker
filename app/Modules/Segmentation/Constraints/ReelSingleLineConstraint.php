<?php

namespace App\Modules\Segmentation\Constraints;

use App\Modules\Layout\Services\FontResolver;

class ReelSingleLineConstraint implements SegmentConstraint
{
    protected FontResolver $fontResolver;

    public function __construct(FontResolver $fontResolver)
    {
        $this->fontResolver = $fontResolver;
    }

    /**
     * Check if the segment fits within the reels single line width constraints.
     *
     * @param array $words
     * @return bool
     */
    public function accepts(array $words): bool
    {
        if (empty($words)) {
            return true;
        }

        // Fallback safety for 1-word segments to prevent deadlocks / empty segments
        if (count($words) === 1) {
            return true;
        }

        $fontSize = config('layouts.reels.font_size', 56);
        $maxTextWidth = config('layouts.reels.max_text_width', 880);

        $firstWord = $words[0];
        $page = $firstWord->page_number;
        $fontPath = $this->fontResolver->resolve($page);

        // Compile glyph text using GlyphStringCompiler
        $lineText = \App\Modules\Shared\Utils\GlyphStringCompiler::compile($words);

        // Measure text bounding box
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $lineText);
        $textWidth = abs($bbox[4] - $bbox[0]);

        return $textWidth <= $maxTextWidth;
    }
}
