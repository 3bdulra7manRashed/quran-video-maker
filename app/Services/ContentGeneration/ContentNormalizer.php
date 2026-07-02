<?php

namespace App\Services\ContentGeneration;

class ContentNormalizer
{
    /**
     * Normalize Arabic text by removing diacritics and simplifying letters.
     */
    public static function normalizeArabic(string $text): string
    {
        // 1. Remove diacritics (harakat): fatha, damma, kasra, sukun, shadda, tanween, superscript alif
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $text);
        
        // 2. Remove Quranic pause marks/symbols
        $text = preg_replace('/[\x{06D6}-\x{06DC}]/u', '', $text);
        
        // 3. Normalize Alif forms
        $text = preg_replace('/[أإآ]/u', 'ا', $text);
        
        // 4. Normalize Ya forms
        $text = preg_replace('/ى/u', 'ي', $text);
        
        // 5. Normalize Ta Marbuta
        $text = preg_replace('/ة/u', 'ه', $text);
        
        // 6. Collapse multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    public static function cleanForComparison(string $text): string
    {
        $normalized = self::normalizeForMatching($text);
        // Keep only characters in the Arabic unicode range (0x0621 - 0x064A), stripping all others (punctuation, space, English)
        return preg_replace('/[^\x{0621}-\x{064A}]/u', '', $normalized);
    }

    /**
     * Normalize translation text (e.g. English) for comparison and preview.
     */
    public static function normalizeTranslation(string $text): string
    {
        // 1. Remove bracketed comments "[...]"
        $text = preg_replace('/\[[^\]]*\]/', '', $text);
        
        // 2. Normalize quotes
        $text = str_replace(['“', '”', '‘', '’'], ['"', '"', "'", "'"], $text);
        
        // 3. Collapse multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 4. Remove spaces before punctuation (e.g. "word ." -> "word.")
        $text = preg_replace('/\s+([.,;:?!])/', '$1', $text);

        return trim($text);
    }

    /**
     * Normalize Tafsir text (strip HTML tags, brackets, and extra spaces).
     */
    public static function normalizeTafsir(string $text): string
    {
        // 1. Remove HTML tags
        $text = strip_tags($text);

        // 2. Remove bracketed comments "[...]"
        $text = preg_replace('/\[[^\]]*\]/', '', $text);
        
        // 3. Normalize quotes
        $text = str_replace(['“', '”', '‘', '’'], ['"', '"', "'", "'"], $text);
        
        // 4. Collapse multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Normalize Arabic text specifically for segment-to-word timing alignment.
     */
    public static function normalizeForMatching(string $text): string
    {
        // 1. Remove ayah numbers and digit sequences (standard, Arabic, Persian)
        $text = preg_replace('/[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}]+/u', '', $text);

        // 2. Remove all tashkeel and Quranic combining marks
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = preg_replace('/\p{Mn}/u', '', $text);

        // 3. Remove Quranic symbols, stop marks, and small letter glyphs (e.g. small ya/waw U+06E5, U+06E6)
        $text = preg_replace('/[۞ۭۚۖۗۘۙۛۢ\x{06DD}\x{06DE}\x{06D6}-\x{06DC}\x{06DF}-\x{06ED}]/u', '', $text);

        // 4. Normalize all forms of alif to bare alif (ا)
        $text = preg_replace('/[أإآٱ]/u', 'ا', $text);

        // 5. Normalize Ya forms (ى -> ي)
        $text = preg_replace('/ى/u', 'ي', $text);

        // 6. Normalize Ta Marbutah forms (ة -> ه)
        $text = preg_replace('/ة/u', 'ه', $text);

        // 7. Remove Tatweel (ـ)
        $text = preg_replace('/ـ/u', '', $text);

        // 8. Collapse multiple spaces into one
        $text = preg_replace('/\s+/', ' ', $text);

        // 8. Trim leading and trailing spaces
        return trim($text);
    }
}
