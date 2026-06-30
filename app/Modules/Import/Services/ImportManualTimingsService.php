<?php

namespace App\Modules\Import\Services;

use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Ayah;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\ReciterWordTiming;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ImportManualTimingsService
{
    /**
     * Import manual word timings.
     *
     * @param array $data Decoded JSON timings data.
     * @param int $reciterId Target reciter database ID.
     * @return array Standardized report.
     */
    public function import(array $data, int $reciterId): array
    {
        $surahNumber = $data['surah'] ?? null;
        $fromAyah = $data['from_ayah'] ?? null;
        $toAyah = $data['to_ayah'] ?? null;
        $jsonWords = $data['words'] ?? [];

        if (!$surahNumber || !$fromAyah || !$toAyah || empty($jsonWords)) {
            throw new InvalidArgumentException("JSON data must contain 'surah', 'from_ayah', 'to_ayah', and a non-empty 'words' array.");
        }

        // Locate Surah
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new InvalidArgumentException("Surah {$surahNumber} not found in database.");
        }

        // Locate Ayahs
        $ayahs = Ayah::where('surah_id', $surah->id)
            ->whereBetween('ayah_number', [$fromAyah, $toAyah])
            ->orderBy('ayah_number')
            ->get();

        if ($ayahs->isEmpty()) {
            throw new InvalidArgumentException("No ayahs found in database for range {$fromAyah}-{$toAyah}.");
        }

        // Query spoken words
        $spokenWords = Word::whereIn('ayah_id', $ayahs->pluck('id'))
            ->where('char_type', 'word')
            ->orderBy('ayah_id')
            ->orderBy('word_index')
            ->get();

        $dbCount = $spokenWords->count();
        $jsonCount = count($jsonWords);

        if ($dbCount !== $jsonCount) {
            throw new InvalidArgumentException("Word count mismatch! Database has {$dbCount} spoken words, but timings file contains {$jsonCount} entries.");
        }

        // Perform sequential alignment
        DB::beginTransaction();
        try {
            $upsertData = [];
            foreach ($spokenWords as $index => $word) {
                $jsonWord = $jsonWords[$index];
                $upsertData[] = [
                    'reciter_id' => $reciterId,
                    'word_id' => $word->id,
                    'start_ms' => (int) $jsonWord['start'],
                    'end_ms' => (int) $jsonWord['end'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            ReciterWordTiming::upsert(
                $upsertData,
                ['reciter_id', 'word_id'],
                ['start_ms', 'end_ms', 'updated_at']
            );

            DB::commit();

            return [
                'success' => true,
                'timedWords' => $dbCount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[ImportManualTimingsService] Database update failed: " . $e->getMessage());
            throw new RuntimeException("Database update failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Text normalization helper (mirrors the original logic).
     */
    public function normalizeText(string $str): string
    {
        $tashkeel = ['ِ', 'ُ', 'َ', 'ْ', 'ّ', 'ً', 'ٌ', 'ٍ', 'ٰ', 'ٓ', 'ۥ', 'ۦ', 'ۧ', 'ۨ', 'ۣ', 'ۥ', 'ۦ'];
        $str = str_replace($tashkeel, '', $str);
        $str = str_replace(['أ', 'إ', 'آ'], 'ا', $str);
        return trim($str);
    }
}
