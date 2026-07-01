<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Rendering\Contracts\TranslationProviderInterface;
use App\Modules\Rendering\Domain\TranslationSegment;
use App\Modules\Segmentation\DTOs\Segment;
use App\Modules\Quran\Models\Word;

class TranslationSegmentBuilder
{
    protected TranslationProviderInterface $provider;

    public function __construct(TranslationProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Convert Arabic segments to TranslationSegments.
     *
     * @param Segment[] $arabicSegments
     * @param int $surahNumber
     * @return TranslationSegment[]
     */
    public function build(array $arabicSegments, int $surahNumber): array
    {
        $translationSegments = [];

        foreach ($arabicSegments as $segment) {
            // Find unique, sorted ayah numbers inside the segment words
            $ayahNumbers = [];
            
            // Check if segment words are loaded as Eloquent models or array
            if (!empty($segment->words)) {
                foreach ($segment->words as $w) {
                    // Check if $w is an array or object
                    if (is_array($w)) {
                        if (isset($w['ayah']['ayah_number'])) {
                            $ayahNumbers[] = (int) $w['ayah']['ayah_number'];
                        }
                    } elseif (is_object($w)) {
                        if (isset($w->ayah->ayah_number)) {
                            $ayahNumbers[] = (int) $w->ayah->ayah_number;
                        }
                    }
                }
            }

            if (empty($ayahNumbers)) {
                // Fallback database query using word IDs
                $ayahNumbers = Word::whereIn('id', $segment->wordIds)
                    ->whereHas('ayah')
                    ->get()
                    ->map(fn($w) => (int) $w->ayah->ayah_number)
                    ->unique()
                    ->toArray();
            } else {
                $ayahNumbers = array_unique($ayahNumbers);
            }

            if (empty($ayahNumbers)) {
                continue;
            }

            sort($ayahNumbers);
            $fromAyah = min($ayahNumbers);
            $toAyah = max($ayahNumbers);

            // Fetch and assemble translations for each ayah in range
            $texts = [];
            for ($ayah = $fromAyah; $ayah <= $toAyah; $ayah++) {
                $texts[] = $this->provider->getAyahTranslation($surahNumber, $ayah);
            }
            $combinedText = implode(' ', array_filter($texts));

            $translationSegments[] = new TranslationSegment(
                $surahNumber,
                $combinedText,
                $fromAyah,
                $toAyah,
                $segment->startMs,
                $segment->endMs
            );
        }

        return $translationSegments;
    }
}
