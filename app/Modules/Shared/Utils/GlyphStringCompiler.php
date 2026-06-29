<?php

namespace App\Modules\Shared\Utils;

class GlyphStringCompiler
{
    /**
     * Compile an array of Word models into a clean RTL-reversed space-separated glyph string
     * where pause markers inside glyph texts are correctly positioned by reversing internal characters,
     * and end markers are kept as independent elements to preserve their end-of-verse positioning.
     *
     * @param array $words Array of Word models.
     * @return string
     */
    public static function compile(array $words): string
    {
        $processed = [];

        foreach ($words as $w) {
            $glyph = $w->glyph_text;
            $len = mb_strlen($glyph);

            if ($len > 1) {
                // If a word has multiple glyph characters (e.g. word + superscript pause marker),
                // reverse the characters internally so that in LTR drawing, the pause marker
                // is rendered to the left of the word.
                $chars = [];
                for ($i = 0; $i < $len; $i++) {
                    $chars[] = mb_substr($glyph, $i, 1);
                }
                $glyph = implode('', array_reverse($chars));
            }

            $processed[] = $glyph;
        }

        // Reverse the sequence of words for LTR drawing
        $reversed = array_reverse($processed);

        return implode(' ', $reversed);
    }
}
