<?php

namespace App\Modules\Dataset\Validation;

interface DatasetValidatorInterface
{
    /**
     * Validate the dataset and return a ValidationReport.
     *
     * @param int $surahNumber
     * @param array $dataset
     * @return ValidationReport
     */
    public function validate(int $surahNumber, array $dataset): ValidationReport;
}
