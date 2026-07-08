<?php

namespace App\Modules\Dataset\Validation;

use Illuminate\Support\Facades\Log;

class DatasetNormalizer
{
    protected GlyphCorrectionRegistry $registry;

    public function __construct(GlyphCorrectionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Normalize the dataset by applying registered correction rules.
     *
     * @param int $surahNumber
     * @param array $dataset Reference to the dataset array.
     * @return CorrectionReport[] Applied correction reports.
     */
    public function normalize(int $surahNumber, array &$dataset): array
    {
        $rules = $this->registry->getRulesForSurah($surahNumber);
        $allReports = [];

        foreach ($rules as $rule) {
            if ($rule->validate($dataset)) {
                Log::info("[DatasetNormalizer] Applying correction rule '{$rule->getIdentifier()}' for Surah {$surahNumber}.");
                $reports = $rule->apply($dataset);
                $allReports = array_merge($allReports, $reports);
            }
        }

        return $allReports;
    }
}
