<?php

namespace App\Modules\Dataset\Services;

use App\Modules\Dataset\Validation\DatasetValidatorInterface;
use App\Modules\Dataset\Validation\DatasetNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class DatasetPipeline
{
    protected DatasetValidatorInterface $validator;
    protected DatasetNormalizer $normalizer;

    public function __construct(
        DatasetValidatorInterface $validator,
        DatasetNormalizer $normalizer
    ) {
        $this->validator = $validator;
        $this->normalizer = $normalizer;
    }

    /**
     * Process the dataset through validation, normalization, and re-validation.
     *
     * @param int $surahNumber
     * @param array $dataset
     * @param \App\Modules\Dataset\Validation\DatasetMetadata|null $metadata
     * @return array The processed (and potentially corrected) dataset.
     * @throws \RuntimeException If validation fails even after normalization.
     */
    public function process(int $surahNumber, array $dataset, ?\App\Modules\Dataset\Validation\DatasetMetadata $metadata = null): array
    {
        Log::info("[DatasetPipeline] Processing dataset for Surah {$surahNumber}...");

        if ($metadata === null) {
            $metadata = new \App\Modules\Dataset\Validation\DatasetMetadata(
                'quran_com',
                'v4',
                now()->toIso8601String(),
                now()->toIso8601String(),
                'glyphs',
                $surahNumber
            );
        }

        // Stage 1: Integrity Validation
        $report = $this->validator->validate($surahNumber, $dataset);
        $correctionReports = [];
        if (!$report->isValid()) {
            Log::warning("[DatasetPipeline] Validation failed on raw dataset for Surah {$surahNumber}. Normalizing...");

            // Stage 2: Normalization / Correction
            $correctionReports = $this->normalizer->normalize($surahNumber, $dataset);

            Log::info("[DatasetPipeline] Applied " . count($correctionReports) . " corrections.");
            foreach ($correctionReports as $reportItem) {
                Log::info(sprintf(
                    "[DatasetPipeline] Rule: %s | Surah: %d | Verse: %d | Field: %s | Old: %s | New: %s | Reason: %s",
                    $reportItem->ruleIdentifier,
                    $reportItem->surahNumber,
                    $reportItem->verseNumber,
                    $reportItem->field,
                    var_export($reportItem->oldValue, true),
                    var_export($reportItem->newValue, true),
                    $reportItem->reason
                ));
            }

            // Stage 3: Re-validation
            $reReport = $this->validator->validate($surahNumber, $dataset);
            if (!$reReport->isValid()) {
                // Stage 4: Fail-safe diagnostic dump
                $logPath = storage_path("logs/dataset_validation_fail_surah_{$surahNumber}.json");
                $logDir = dirname($logPath);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }

                $issuesArray = array_map(fn($issue) => $issue->toArray(), $reReport->getIssues());
                $correctionsArray = array_map(fn($c) => $c->toArray(), $correctionReports);

                File::put($logPath, json_encode([
                    'surah' => $surahNumber,
                    're_validation_issues' => $issuesArray,
                    'applied_corrections' => $correctionsArray,
                ], JSON_PRETTY_PRINT));

                Log::error("[DatasetPipeline] Re-validation failed. Diagnostic report written to: {$logPath}");

                throw new \RuntimeException(
                    "Dataset validation failed for Surah {$surahNumber} even after normalization. " .
                    "Check diagnostic log at: {$logPath}"
                );
            }

            Log::info("[DatasetPipeline] Normalization successful. Dataset is now clean.");
        } else {
            Log::info("[DatasetPipeline] Dataset is already clean. No normalization required.");
        }

        // Calculate integrity hash on normalized verses
        $integrityHash = hash('sha256', json_encode($dataset['verses'], JSON_UNESCAPED_UNICODE));
        $metadata = $metadata->withIntegrityHash($integrityHash);

        // Store metadata alongside the dataset verses
        $dataset['metadata'] = $metadata->toArray();

        return $dataset;
    }
}
