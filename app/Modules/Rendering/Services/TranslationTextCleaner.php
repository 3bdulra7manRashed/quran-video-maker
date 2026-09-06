<?php

namespace App\Modules\Rendering\Services;

class TranslationTextCleaner
{
    /**
     * Clean English translation by removing explanatory text in square brackets
     * and normalizing spaces/punctuation.
     *
     * @param string $text
     * @return string
     */
    public static function clean(string $text): string
    {
        // 0. Remove footnote tags and their contents e.g. <sup foot_note=1>1</sup>
        $cleaned = preg_replace('/<sup\b[^>]*>.*?<\/sup>/is', '', $text);

        // 0.1 Strip any remaining HTML tags
        $cleaned = strip_tags($cleaned);

        // 1. Remove all bracketed text including the brackets
        $cleaned = preg_replace('/\[[^\]]*\]/', '', $cleaned);

        // 2. Remove spaces before punctuation (.,;:!?)
        $cleaned = preg_replace('/\s+([.,;:!?])/', '$1', $cleaned);

        // 3. Collapse multiple spaces into a single space
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        // 4. Clean up punctuation artifacts (like double commas left over from removal)
        $cleaned = preg_replace('/,+/is', ',', $cleaned);

        // 5. Trim leading and trailing spaces
        return trim($cleaned);
    }
}
