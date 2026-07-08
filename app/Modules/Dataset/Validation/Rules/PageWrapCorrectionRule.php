<?php

namespace App\Modules\Dataset\Validation\Rules;

use App\Modules\Dataset\Validation\CorrectionReport;

class PageWrapCorrectionRule implements GlyphCorrectionRule
{
    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('dataset_corrections.page_wraps', []);
    }

    public function getIdentifier(): string
    {
        return 'page_wrap_correction_rule';
    }

    public function getDescription(): string
    {
        return 'Corrects page numbers at page-wrap boundaries using configuration files.';
    }

    public function priority(): int
    {
        return 100;
    }

    public function supportsSurah(int $surahNumber): bool
    {
        return isset($this->config[$surahNumber]);
    }

    public function validate(array $dataset): bool
    {
        if (empty($dataset['verses']) || !is_array($dataset['verses'])) {
            return false;
        }

        $vKey = $dataset['verses'][0]['verse_key'] ?? '';
        if (!$vKey) {
            return false;
        }

        $surah = (int) explode(':', $vKey)[0];
        if (!$this->supportsSurah($surah)) {
            return false;
        }

        $corrections = $this->config[$surah] ?? [];
        foreach ($dataset['verses'] as $verse) {
            $vNum = $verse['verse_number'] ?? null;
            if ($vNum !== null && isset($corrections[$vNum])) {
                $target = $corrections[$vNum];
                if (isset($target['page_number']) && ($verse['page_number'] ?? null) !== $target['page_number']) {
                    // Mismatch found, correction needed
                    return true;
                }
            }
        }

        return false;
    }

    public function apply(array &$dataset): array
    {
        if (empty($dataset['verses']) || !is_array($dataset['verses'])) {
            return [];
        }

        $vKey = $dataset['verses'][0]['verse_key'] ?? '';
        if (!$vKey) {
            return [];
        }

        $surah = (int) explode(':', $vKey)[0];
        if (!$this->supportsSurah($surah)) {
            return [];
        }

        $corrections = $this->config[$surah] ?? [];
        $reports = [];

        foreach ($dataset['verses'] as &$verse) {
            $vNum = $verse['verse_number'] ?? null;
            if ($vNum !== null && isset($corrections[$vNum])) {
                $target = $corrections[$vNum];
                if (isset($target['page_number'])) {
                    $oldPage = $verse['page_number'] ?? null;
                    $newPage = $target['page_number'];

                    if ($oldPage !== $newPage) {
                        $verse['page_number'] = $newPage;

                        // Correct page_number for every word in this verse
                        if (isset($verse['words']) && is_array($verse['words'])) {
                            foreach ($verse['words'] as &$w) {
                                $w['page_number'] = $newPage;
                            }
                        }

                        $reports[] = new CorrectionReport(
                            $this->getIdentifier(),
                            $surah,
                            $vNum,
                            'page_number',
                            $oldPage,
                            $newPage,
                            "Corrected page_number at page wrap boundary from $oldPage to $newPage"
                        );
                    }
                }
            }
        }

        return $reports;
    }
}
