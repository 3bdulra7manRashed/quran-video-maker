<?php

namespace App\Modules\Rendering\Contracts;

interface TranslationProviderInterface
{
    /**
     * Get translation text of a single ayah.
     *
     * @param int $surahNumber
     * @param int $ayahNumber
     * @return string
     */
    public function getAyahTranslation(int $surahNumber, int $ayahNumber): string;

    /**
     * Get translations of a full surah (keyed by ayah number).
     *
     * @param int $surahNumber
     * @return array
     */
    public function getAyahTranslations(int $surahNumber): array;

    /**
     * Get the source identifier for the provider (e.g. 'sahih_international').
     *
     * @return string
     */
    public function source(): string;
}
