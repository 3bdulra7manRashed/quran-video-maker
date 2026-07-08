<?php

namespace App\Modules\Dataset\Validation;

use App\Modules\Dataset\Validation\Rules\GlyphCorrectionRule;

class GlyphCorrectionRegistry
{
    /**
     * @var GlyphCorrectionRule[]
     */
    protected array $rules = [];

    public function register(GlyphCorrectionRule $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * Get sorted rules supporting a specific surah.
     *
     * @param int $surahNumber
     * @return GlyphCorrectionRule[]
     */
    public function getRulesForSurah(int $surahNumber): array
    {
        $filtered = array_filter($this->rules, fn($rule) => $rule->supportsSurah($surahNumber));

        // Sort rules by priority (ascending order: lower priority numbers run first)
        usort($filtered, fn($a, $b) => $a->priority() <=> $b->priority());

        return $filtered;
    }

    /**
     * @return GlyphCorrectionRule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
