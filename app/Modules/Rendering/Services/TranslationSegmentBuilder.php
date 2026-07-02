<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Rendering\Contracts\TranslationProviderInterface;
use App\Modules\Rendering\Domain\TranslationSegment;
use App\Modules\Segmentation\DTOs\Segment;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\Ayah;

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

        // 1. Load all translations for this Surah in a single query
        $rawTranslationsMap = $this->provider->getAyahTranslations($surahNumber);
        $translationsMap = [];
        foreach ($rawTranslationsMap as $ayahNumber => $text) {
            $translationsMap[$ayahNumber] = TranslationTextCleaner::clean($text);
        }

        // 2. Preload all ayahs and their spoken words for this Surah
        $ayahs = Ayah::where('surah_id', function ($q) use ($surahNumber) {
            $q->select('id')->from('surahs')->where('number', $surahNumber);
        })->with(['words' => function ($q) {
            $q->where('char_type', 'word')->orderBy('word_index');
        }])->get();

        $ayahsMap = [];
        $totalAyahWordCountMap = []; // maps ayah_id -> count of spoken words
        $wordPositionInAyahMap = []; // maps word_id -> 0-indexed position in ayah

        foreach ($ayahs as $ayah) {
            $ayahsMap[$ayah->id] = $ayah;
            $spokenWords = $ayah->words->all();
            $totalAyahWordCountMap[$ayah->id] = count($spokenWords);
            
            foreach ($spokenWords as $position => $word) {
                $wordPositionInAyahMap[$word->id] = $position;
            }
        }

        // 3. Load all words of this Surah to avoid database queries in the loop
        $allWordsMap = Word::whereHas('ayah', function ($q) use ($surahNumber) {
            $q->where('surah_id', function ($sub) use ($surahNumber) {
                $sub->select('id')->from('surahs')->where('number', $surahNumber);
            });
        })->get()->keyBy('id');

        foreach ($arabicSegments as $segment) {
            // Find segment words and group them by ayah
            $segmentSpokenWords = [];
            foreach ($segment->wordIds as $wordId) {
                $w = $allWordsMap[$wordId] ?? null;
                if ($w && $w->char_type === 'word') {
                    $segmentSpokenWords[] = $w;
                }
            }

            $wordsByAyah = [];
            foreach ($segmentSpokenWords as $w) {
                $ayah = $ayahsMap[$w->ayah_id] ?? null;
                if ($ayah) {
                    $wordsByAyah[$ayah->ayah_number][] = $w;
                }
            }

            // Sort ayahs in the segment
            ksort($wordsByAyah);

            $texts = [];
            $ayahNumbers = [];

            if (!empty($wordsByAyah)) {
                foreach ($wordsByAyah as $ayahNumber => $segAyahWords) {
                    $ayahRecord = $ayahsMap[$segAyahWords[0]->ayah_id] ?? null;
                    if (!$ayahRecord) {
                        continue;
                    }

                    $ayahNumbers[] = $ayahNumber;
                    $fullTranslationText = $translationsMap[$ayahNumber] ?? TranslationTextCleaner::clean($this->provider->getAyahTranslation($surahNumber, $ayahNumber));

                    $totalAyahWordCount = $totalAyahWordCountMap[$ayahRecord->id] ?? 0;
                    $segmentAyahWordCount = count($segAyahWords);

                    if ($segmentAyahWordCount > 0 && $segmentAyahWordCount < $totalAyahWordCount) {
                        // Partial ayah segment! Invoke TranslationChunker
                        usort($segAyahWords, fn($a, $b) => $a->word_index <=> $b->word_index);
                        $firstWord = $segAyahWords[0];
                        $lastWord = end($segAyahWords);

                        $arabicStartWordIndex = $wordPositionInAyahMap[$firstWord->id] ?? 0;
                        $arabicEndWordIndex = $wordPositionInAyahMap[$lastWord->id] ?? 0;

                        $arabicWordsText = implode(' ', array_map(fn($w) => $w->plain_text, $segAyahWords));

                        $texts[] = TranslationChunker::chunk(
                            $arabicWordsText,
                            $fullTranslationText,
                            $arabicStartWordIndex,
                            $arabicEndWordIndex,
                            $totalAyahWordCount
                        );
                    } else {
                        // Complete ayah segment
                        $texts[] = $fullTranslationText;
                    }
                }
            } else {
                // Backward-compatible fallback for segments containing no spoken words (e.g. only end markers)
                $fallbackAyahIds = [];
                foreach ($segment->wordIds as $wordId) {
                    $w = $allWordsMap[$wordId] ?? null;
                    if ($w) {
                        $fallbackAyahIds[] = $w->ayah_id;
                    }
                }
                $fallbackAyahIds = array_unique($fallbackAyahIds);

                foreach ($fallbackAyahIds as $id) {
                    $ayah = $ayahsMap[$id] ?? null;
                    if ($ayah) {
                        $ayahNumbers[] = $ayah->ayah_number;
                        $texts[] = $translationsMap[$ayah->ayah_number] ?? TranslationTextCleaner::clean($this->provider->getAyahTranslation($surahNumber, $ayah->ayah_number));
                    }
                }
                sort($ayahNumbers);
            }

            if (empty($ayahNumbers)) {
                continue;
            }

            $fromAyah = min($ayahNumbers);
            $toAyah = max($ayahNumbers);
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
