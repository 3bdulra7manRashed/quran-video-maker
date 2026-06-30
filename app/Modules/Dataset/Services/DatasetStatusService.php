<?php

namespace App\Modules\Dataset\Services;

use App\Modules\Quran\Models\AudioFile;
use App\Modules\Quran\Models\Ayah;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\ReciterWordTiming;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Word;
use App\Modules\Shared\Services\QuranPathResolver;

class DatasetStatusService
{
    protected QuranPathResolver $pathResolver;

    public function __construct(QuranPathResolver $pathResolver)
    {
        $this->pathResolver = $pathResolver;
    }

    /**
     * Evaluate dataset readiness for a given reciter + surah combination.
     *
     * @param string $reciterSlug
     * @param int $surahNumber
     * @return array
     */
    public function evaluate(string $reciterSlug, int $surahNumber): array
    {
        $reciter = Reciter::where('slug', $reciterSlug)->first();
        $surah = Surah::where('number', $surahNumber)->first();

        $glyphs = $this->evaluateGlyphs($surah);
        $audio = $this->evaluateAudio($reciter, $surahNumber);
        $timings = $this->evaluateTimings($reciter, $surah);

        $renderable = $glyphs && $audio;

        return [
            'reciter' => $reciterSlug,
            'surah' => $surahNumber,
            'glyphs' => $glyphs,
            'audio' => $audio,
            'timings' => $timings,
            'renderable' => $renderable,
        ];
    }

    /**
     * Glyphs are available when:
     * - Surah exists in DB
     * - Ayahs exist for this Surah
     * - At least one spoken word (char_type = 'word') exists
     */
    protected function evaluateGlyphs(?Surah $surah): bool
    {
        if (!$surah) {
            return false;
        }

        $ayahIds = Ayah::where('surah_id', $surah->id)->pluck('id');
        if ($ayahIds->isEmpty()) {
            return false;
        }

        $spokenWordCount = Word::whereIn('ayah_id', $ayahIds)
            ->where('char_type', 'word')
            ->count();

        return $spokenWordCount > 0;
    }

    /**
     * Audio is available when:
     * - Reciter exists in DB
     * - AudioFile record exists for the reciter + surah
     * - Physical MP3 file exists on disk
     */
    protected function evaluateAudio(?Reciter $reciter, int $surahNumber): bool
    {
        if (!$reciter) {
            return false;
        }

        $audioFile = AudioFile::where('reciter_id', $reciter->id)
            ->where('surah_number', $surahNumber)
            ->first();

        if (!$audioFile) {
            return false;
        }

        $absolutePath = $this->pathResolver->audio($audioFile->file_path);

        return file_exists($absolutePath);
    }

    /**
     * Evaluate timing status across four states:
     * - none: no words have timings
     * - partial: some words have timings
     * - full: all spoken words have timings
     * - fallback: no real timings but renderer can use proportional fallback
     *
     * Returns an array with mode, timedWords, and totalWords.
     */
    protected function evaluateTimings(?Reciter $reciter, ?Surah $surah): array
    {
        if (!$surah) {
            return [
                'mode' => 'none',
                'timedWords' => 0,
                'totalWords' => 0,
            ];
        }

        $ayahIds = Ayah::where('surah_id', $surah->id)->pluck('id');
        if ($ayahIds->isEmpty()) {
            return [
                'mode' => 'none',
                'timedWords' => 0,
                'totalWords' => 0,
            ];
        }

        $totalSpoken = Word::whereIn('ayah_id', $ayahIds)
            ->where('char_type', 'word')
            ->count();

        if ($totalSpoken === 0) {
            return [
                'mode' => 'none',
                'timedWords' => 0,
                'totalWords' => 0,
            ];
        }

        if (!$reciter) {
            return [
                'mode' => 'none',
                'timedWords' => 0,
                'totalWords' => $totalSpoken,
            ];
        }

        $wordIds = Word::whereIn('ayah_id', $ayahIds)
            ->where('char_type', 'word')
            ->pluck('id');

        $timedSpoken = ReciterWordTiming::where('reciter_id', $reciter->id)
            ->whereIn('word_id', $wordIds)
            ->count();

        if ($timedSpoken === 0) {
            return [
                'mode' => 'none',
                'timedWords' => 0,
                'totalWords' => $totalSpoken,
            ];
        }

        if ($timedSpoken === $totalSpoken) {
            return [
                'mode' => 'full',
                'timedWords' => $timedSpoken,
                'totalWords' => $totalSpoken,
            ];
        }

        return [
            'mode' => 'partial',
            'timedWords' => $timedSpoken,
            'totalWords' => $totalSpoken,
        ];
    }
}
