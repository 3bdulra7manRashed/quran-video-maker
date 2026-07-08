<?php

namespace App\Modules\Dataset\Validation\Rules;

interface GlyphCorrectionRule
{
    public function getIdentifier(): string;

    public function getDescription(): string;

    public function priority(): int;

    public function supportsSurah(int $surahNumber): bool;

    /**
     * Determine if correction rule should execute.
     *
     * @param array $dataset
     * @return bool
     */
    public function validate(array $dataset): bool;

    /**
     * Apply correction rule on the dataset array in-place.
     *
     * @param array $dataset Reference to the dataset array.
     * @return \App\Modules\Dataset\Validation\CorrectionReport[]
     */
    public function apply(array &$dataset): array;
}
