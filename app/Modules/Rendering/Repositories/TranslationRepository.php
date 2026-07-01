<?php

namespace App\Modules\Rendering\Repositories;

use App\Modules\Rendering\Contracts\TranslationRepositoryInterface;
use App\Modules\Quran\Models\Word;

class TranslationRepository implements TranslationRepositoryInterface
{
    /**
     * Fetch translation of a single ayah by its source.
     */
    public function getAyahTranslation(int $surahNumber, int $ayahNumber, string $source): string
    {
        // Currently we fall back to concatenating word-level English translations from the words table.
        // In the future, this repository will load from dedicated translation databases or JSON files.
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
