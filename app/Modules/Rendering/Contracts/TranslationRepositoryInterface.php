<?php

namespace App\Modules\Rendering\Contracts;

interface TranslationRepositoryInterface
{
    /**
     * Fetch translation of a single ayah by its source.
     *
     * @param int $surahNumber
     * @param int $ayahNumber
     * @param string $source
     * @return string
     */
    public function getAyahTranslation(int $surahNumber, int $ayahNumber, string $source): string;

    /**
     * Fetch translations of a full surah by its source (keyed by ayah number).
     *
     * @param int $surahNumber
     * @param string $source
     * @return array
     */
    public function getAyahTranslations(int $surahNumber, string $source): array;
}
