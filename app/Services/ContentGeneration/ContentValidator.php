<?php

namespace App\Services\ContentGeneration;

use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\AyahTranslation;

class ContentValidator
{
    /**
     * Validate the pre-generated JSON content against official data in the database.
     *
     * @param array $data Decoded JSON array.
     * @param int $surahNumber
     * @param int|null $fromAyah
     * @param int|null $toAyah
     * @return array{errors: string[], warnings: string[], isValid: bool}
     */
    public static function validate(
        array $data,
        int $surahNumber,
        ?int $fromAyah = null,
        ?int $toAyah = null
    ): array {
        $errors = [];
        $warnings = [];

        // 1. Structural checks
        if (!isset($data['segments']) || !is_array($data['segments'])) {
            return [
                'errors' => ['JSON structure invalid. Missing "segments" array.'],
                'warnings' => [],
                'isValid' => false,
            ];
        }

        $segments = $data['segments'];
        if (empty($segments)) {
            return [
                'errors' => ['"segments" array cannot be empty.'],
                'warnings' => [],
                'isValid' => false,
            ];
        }

        $orders = [];
        $combinedArabic = '';
        $combinedTranslation = '';

        foreach ($segments as $idx => $seg) {
            $num = $idx + 1;
            
            // Check keys presence
            if (!isset($seg['order'])) {
                $errors[] = "Segment #{$num} is missing required field: \"order\".";
            } else {
                $orders[] = (int)$seg['order'];
            }

            if (!isset($seg['arabic']) || trim($seg['arabic']) === '') {
                $errors[] = "Segment #{$num} is missing or has empty \"arabic\" text.";
            } else {
                $combinedArabic .= ' ' . $seg['arabic'];
            }

            if (!isset($seg['translation']) || trim($seg['translation']) === '') {
                $errors[] = "Segment #{$num} is missing or has empty \"translation\" text.";
            } else {
                $combinedTranslation .= ' ' . $seg['translation'];
            }

            if (!isset($seg['tafsir']) || trim($seg['tafsir']) === '') {
                $warnings[] = "Segment #{$num} has empty \"tafsir\" text.";
            }
        }

        // 2. Validate ordering sequence
        if (empty($errors)) {
            sort($orders);
            $expected = range(1, count($segments));
            if ($orders !== $expected) {
                $errors[] = "Segment ordering is invalid. Must be sequential starting from 1 with no gaps or duplicates. Received: " . implode(', ', $orders);
            }
        }

        // 3. Compare content with the official database records
        if (empty($errors)) {
            // Load official words
            $ayahQuery = \App\Modules\Quran\Models\Ayah::where('surah_id', function ($q) use ($surahNumber) {
                $q->select('id')->from('surahs')->where('number', $surahNumber);
            });
            if ($fromAyah !== null) {
                $ayahQuery->where('ayah_number', '>=', $fromAyah);
            }
            if ($toAyah !== null) {
                $ayahQuery->where('ayah_number', '<=', $toAyah);
            }
            $ayahIds = $ayahQuery->pluck('id');
            $words = Word::whereIn('ayah_id', $ayahIds)
                ->orderBy('page_number')
                ->orderBy('line_number')
                ->orderBy('ayah_id')
                ->orderBy('word_index')
                ->get();

            // Match raw Arabic letter sequence (excluding vowels, space, punctuation)
            $officialArabicNorm = ContentNormalizer::cleanForComparison(
                implode('', array_map(fn($w) => $w->uthmani_text, $words->all()))
            );
            $jsonArabicNorm = ContentNormalizer::cleanForComparison($combinedArabic);

            if ($officialArabicNorm !== $jsonArabicNorm) {
                $errors[] = "The Arabic text in the JSON does not match the official Quranic text. Make sure you haven't added, removed, or modified any Arabic letters.";
            }

            // Compare translations (normalized)
            $officialTranslations = AyahTranslation::where('source', 'sahih_international')
                ->where('surah_number', $surahNumber);
            if ($fromAyah !== null) {
                $officialTranslations->where('ayah_number', '>=', $fromAyah);
            }
            if ($toAyah !== null) {
                $officialTranslations->where('ayah_number', '<=', $toAyah);
            }
            $officialTransText = implode(' ', $officialTranslations->orderBy('ayah_number')->pluck('text')->all());

            $officialTransNorm = ContentNormalizer::normalizeTranslation($officialTransText);
            $jsonTransNorm = ContentNormalizer::normalizeTranslation($combinedTranslation);

            if ($officialTransNorm !== $jsonTransNorm) {
                $warnings[] = "The translation text deviates from the official Sahih International translation. Please verify you preserved the official translation text.";
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'isValid' => empty($errors),
        ];
    }
}
