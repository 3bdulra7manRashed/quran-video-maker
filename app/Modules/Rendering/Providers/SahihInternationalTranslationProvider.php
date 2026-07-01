<?php

namespace App\Modules\Rendering\Providers;

use App\Modules\Rendering\Contracts\TranslationProviderInterface;
use App\Modules\Rendering\Contracts\TranslationRepositoryInterface;

class SahihInternationalTranslationProvider implements TranslationProviderInterface
{
    protected TranslationRepositoryInterface $repository;

    public function __construct(TranslationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get translation text of a single ayah.
     */
    public function getAyahTranslation(int $surahNumber, int $ayahNumber): string
    {
        return $this->repository->getAyahTranslation($surahNumber, $ayahNumber, $this->source());
    }

    public function getAyahTranslations(int $surahNumber): array
    {
        return $this->repository->getAyahTranslations($surahNumber, $this->source());
    }

    /**
     * Get the source identifier for the provider.
     */
    public function source(): string
    {
        return 'sahih_international';
    }
}
