<?php

namespace App\Modules\Rendering\Services;

class TranslationChunker
{
    /**
     * Slice English translation proportionally to match Arabic word range.
     *
     * @param string $arabicText Full Arabic ayah text (or space-separated words)
     * @param string $englishText Full English translation text
     * @param int $arabicStartWordIndex 0-indexed start of Arabic word range (relative to ayah)
     * @param int $arabicEndWordIndex 0-indexed end of Arabic word range (relative to ayah)
     * @param int $totalArabicWords Total number of Arabic words in the ayah
     * @return string Proportional slice of the English translation
     */
    public static function chunk(
        string $arabicText,
        string $englishText,
        int $arabicStartWordIndex,
        int $arabicEndWordIndex,
        int $totalArabicWords
    ): string {
        $englishText = trim($englishText);
        if ($englishText === '') {
            return '';
        }

        // Split English text into words on space boundaries
        $englishWords = preg_split('/\s+/', $englishText);
        $totalEnglishWords = count($englishWords);

        if ($totalEnglishWords === 0) {
            return '';
        }

        if ($totalArabicWords <= 0) {
            $totalArabicWords = 1;
        }

        // Clamping inputs safely to prevent negative or out of bounds index values
        $arabicStartWordIndex = max(0, min($arabicStartWordIndex, $totalArabicWords - 1));
        $arabicEndWordIndex = max($arabicStartWordIndex, min($arabicEndWordIndex, $totalArabicWords - 1));

        // Fast path: if word counts are equal, slice directly
        if ($totalArabicWords === $totalEnglishWords) {
            $englishStartIndex = $arabicStartWordIndex;
            $englishEndIndex = $arabicEndWordIndex + 1;
        } else {
            // Proportional slicing calculations
            $englishStartIndex = (int) floor(
                $arabicStartWordIndex / $totalArabicWords * $totalEnglishWords
            );
            $englishEndIndex = (int) ceil(
                ($arabicEndWordIndex + 1) / $totalArabicWords * $totalEnglishWords
            );
        }

        // Clamp indices safely
        $englishStartIndex = max(0, min($englishStartIndex, $totalEnglishWords - 1));
        $englishEndIndex = max($englishStartIndex + 1, min($englishEndIndex, $totalEnglishWords));

        $chunkWords = array_slice(
            $englishWords,
            $englishStartIndex,
            $englishEndIndex - $englishStartIndex
        );

        // Defensive fallback: return at least one word
        if (empty($chunkWords) && $totalEnglishWords > 0) {
            $chunkWords = [$englishWords[0]];
        }

        return implode(' ', $chunkWords);
    }
}
