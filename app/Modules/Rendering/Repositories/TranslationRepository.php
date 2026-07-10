<?php

namespace App\Modules\Rendering\Repositories;

use App\Modules\Rendering\Contracts\TranslationRepositoryInterface;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\AyahTranslation;

class TranslationRepository implements TranslationRepositoryInterface
{
    /**
     * Fetch translation of a single ayah by its source.
     */
    public function getAyahTranslation(int $surahNumber, int $ayahNumber, string $source): string
    {
        $row = AyahTranslation::where('source', $source)
            ->where('surah_number', $surahNumber)
            ->where('ayah_number', $ayahNumber)
            ->first();

        if ($row) {
            return $this->cleanTranslationText($row->text);
        }

        return '';
    }

    /**
     * Fetch translations of a full surah by its source (keyed by ayah number).
     */
    public function getAyahTranslations(int $surahNumber, string $source): array
    {
        $rows = AyahTranslation::where('source', $source)
            ->where('surah_number', $surahNumber)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->ayah_number] = $this->cleanTranslationText($row->text);
        }

        return $result;
    }

    /**
     * Strip HTML tags and footnotes (e.g. <sup>...</sup>) before rendering.
     */
    protected function cleanTranslationText(string $text): string
    {
        // First remove <sup> tags and their content completely
        $cleaned = preg_replace('/<sup\b[^>]*>.*?<\/sup>/is', '', $text);
        // Strip any remaining HTML tags
        $cleaned = strip_tags($cleaned);
        
        // Normalize extended Latin transliteration characters that standard English fonts (like Georgia) do not support
        $replacements = [
            'ḥ' => 'h', 'Ḥ' => 'H',
            'ā' => 'a', 'Ā' => 'A',
            'ī' => 'i', 'Ī' => 'I',
            'ū' => 'u', 'Ū' => 'U',
            'ṣ' => 's', 'Ṣ' => 'S',
            'ḍ' => 'd', 'Ḍ' => 'D',
            'ṭ' => 't', 'Ṭ' => 'T',
            'ẓ' => 'z', 'Ẓ' => 'Z',
            'ʿ' => "'", 'ʾ' => "'",
            '’' => "'", '‘' => "'",
        ];
        $cleaned = strtr($cleaned, $replacements);
        
        // Remove extra/duplicate spaces
        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * Fallback to concatenating word-level English translations from the words table.
     */
    protected function fallbackTranslation(int $surahNumber, int $ayahNumber): string
    {
        $translation = Word::whereHas('ayah', function ($q) use ($surahNumber, $ayahNumber) {
            $q->where('ayah_number', $ayahNumber)
              ->where('surah_id', function ($sub) use ($surahNumber) {
                  $sub->select('id')->from('surahs')->where('number', $surahNumber);
              });
        })
        ->where('char_type', 'word')
        ->orderBy('word_index')
        ->get()
        ->pluck('translation_en')
        ->map(fn($val) => is_string($val) ? trim($val) : '')
        ->filter(fn($val) => $val !== '')
        ->join(' ');

        return $translation !== '' ? $translation : '';
    }
}
