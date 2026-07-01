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
}
