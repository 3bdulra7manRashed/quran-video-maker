<?php

namespace App\Modules\Rendering\Services;

use App\Services\ContentGeneration\ContentNormalizer;

class TranslationTextCleaner
{
    /**
     * Clean English translation by removing explanatory text in square brackets,
     * normalizing transliteration symbols, and cleaning spaces/punctuation.
     *
     * @param string $text
     * @return string
     */
    public static function clean(string $text): string
    {
        return ContentNormalizer::normalizeTranslation($text);
    }
}
