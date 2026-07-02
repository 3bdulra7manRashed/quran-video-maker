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

    /**
     * Normalize Arabic text to contain ONLY simplified letters (no punctuation, no spaces, no non-Arabic characters) for comparison.
     */
    public static function cleanForComparison(string $text): string
    {
        $normalized = self::normalizeArabic($text);
        // Keep only characters in the Arabic unicode range (0x0600 - 0x06FF), stripping all others (punctuation, space, English)
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
}
