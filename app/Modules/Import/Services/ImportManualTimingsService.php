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

            // Store coverage metadata
            \App\Modules\Quran\Models\DatasetMetadata::updateOrCreate([
                'reciter_id' => $reciterId,
                'surah_number' => $surahNumber,
            ], [
                'from_ayah' => $fromAyah ? (int) $fromAyah : null,
                'to_ayah' => $toAyah ? (int) $toAyah : null,
            ]);

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
     * Import manual Mushaf line timings (page_number + line_number).
     *
     * @param array $data Decoded JSON line timings data.
     * @param int $reciterId Target reciter database ID.
     * @return array Standardized report.
     */
    public function importLines(array $data, int $reciterId): array
    {
        $surahNumber = $data['surah'] ?? null;
        $jsonLines = $data['lines'] ?? [];

        if (!$surahNumber || empty($jsonLines)) {
            throw new \InvalidArgumentException("JSON data must contain 'surah' and a non-empty 'lines' array.");
        }

        DB::beginTransaction();
        try {
            foreach ($jsonLines as $line) {
                \App\Modules\Quran\Models\ReciterLineTiming::updateOrCreate([
                    'reciter_id' => $reciterId,
                    'surah_number' => $surahNumber,
                    'page_number' => (int) $line['page_number'],
                    'line_number' => (int) $line['line_number'],
                ], [
                    'start_ms' => (int) $line['start'],
                ]);
            }

            // Store coverage metadata
            $fromAyah = isset($data['from_ayah']) ? (int) $data['from_ayah'] : null;
            $toAyah = isset($data['to_ayah']) ? (int) $data['to_ayah'] : null;

            \App\Modules\Quran\Models\DatasetMetadata::updateOrCreate([
                'reciter_id' => $reciterId,
                'surah_number' => $surahNumber,
            ], [
                'from_ayah' => $fromAyah,
                'to_ayah' => $toAyah,
            ]);

            DB::commit();

            return [
                'success' => true,
                'timedLines' => count($jsonLines),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[ImportManualTimingsService] Lines import database update failed: " . $e->getMessage());
            throw new RuntimeException("Lines database update failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Import manual segment timings, mapping segments to database words.
     *
     * @param array $data Decoded JSON segment timings data.
     * @param int $reciterId Target reciter database ID.
     * @return array Standardized report.
     */
    public function importSegments(array $data, int $reciterId): array
    {
        $surahNumber = $data['surah'] ?? null;
        $jsonSegments = $data['segments'] ?? [];

        if (!$surahNumber || empty($jsonSegments)) {
            throw new \InvalidArgumentException("JSON data must contain 'surah' and a non-empty 'segments' array.");
        }

        // Fetch reciter
        $reciter = \App\Modules\Quran\Models\Reciter::find($reciterId);
        if (!$reciter) {
            throw new \InvalidArgumentException("Reciter ID {$reciterId} not found.");
        }

        // Fetch approved segments from reels_generated_content
        $approvedSegments = \App\Modules\Quran\Models\ReelsGeneratedContent::where('reciter_id', $reciterId)
            ->where('surah_number', $surahNumber)
            ->orderBy('segment_order')
            ->get();

        if ($approvedSegments->isEmpty()) {
            throw new \RuntimeException("No approved segment definitions found for this Surah. Please generate and approve segments first.");
        }

        // Fetch Surah & Words
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new \InvalidArgumentException("Surah {$surahNumber} not found.");
        }

        $ayahIds = $surah->ayahs()->pluck('id');
        $words = Word::with('ayah')->whereIn('ayah_id', $ayahIds)
            ->orderBy('page_number')
            ->orderBy('line_number')
            ->orderBy('ayah_id')
            ->orderBy('word_index')
            ->get();

        // Align segments with database words
        $aligner = app(\App\Services\ContentGeneration\GeneratedContentWordAligner::class);
        $alignedRanges = $aligner->align($approvedSegments, $words);

        // Map uploaded segment start times
        $uploadedMap = [];
        foreach ($jsonSegments as $item) {
            $uploadedMap[(int)$item['segment_order']] = (int)$item['start'];
        }

        // Fetch total audio file duration to calculate bounds for the last segment
        $audioFile = \App\Modules\Quran\Models\AudioFile::where('reciter_id', $reciterId)
            ->where('surah_number', $surahNumber)
            ->first();
        $totalDurationMs = $audioFile ? (int)$audioFile->duration_ms : null;

        $upsertData = [];
        $totalTimedWords = 0;

        DB::beginTransaction();
        try {
            foreach ($alignedRanges as $range) {
                $order = $range['segmentOrder'];
                $rangeWords = $range['words'];
                $spokenWords = [];
                foreach ($rangeWords as $w) {
                    if ($w->char_type === 'word') {
                        $spokenWords[] = $w;
                    }
                }

                $wordCount = count($spokenWords);
                if ($wordCount === 0) {
                    continue;
                }

                // Retrieve segment start/end
                $startMs = $uploadedMap[$order] ?? 0;
                $nextStart = $uploadedMap[$order + 1] ?? null;
                $endMs = $nextStart !== null ? $nextStart : ($totalDurationMs !== null ? $totalDurationMs : ($startMs + 5000));

                if ($endMs < $startMs) {
                    $endMs = $startMs;
                }

                $segmentDuration = $endMs - $startMs;
                $wordDuration = $segmentDuration / $wordCount;

                foreach ($spokenWords as $j => $word) {
                    $wStart = (int)round($startMs + ($j * $wordDuration));
                    $wEnd = (int)round($startMs + (($j + 1) * $wordDuration));

                    $upsertData[] = [
                        'reciter_id' => $reciterId,
                        'word_id' => $word->id,
                        'start_ms' => $wStart,
                        'end_ms' => $wEnd,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $totalTimedWords++;
                }
            }

            if (!empty($upsertData)) {
                ReciterWordTiming::upsert(
                    $upsertData,
                    ['reciter_id', 'word_id'],
                    ['start_ms', 'end_ms', 'updated_at']
                );
            }

            // Store coverage metadata
            $fromAyah = $data['from_ayah'] ?? null;
            $toAyah = $data['to_ayah'] ?? null;

            \App\Modules\Quran\Models\DatasetMetadata::updateOrCreate([
                'reciter_id' => $reciterId,
                'surah_number' => $surahNumber,
            ], [
                'from_ayah' => $fromAyah ? (int)$fromAyah : null,
                'to_ayah' => $toAyah ? (int)$toAyah : null,
            ]);

            DB::commit();

            return [
                'success' => true,
                'timedWords' => $totalTimedWords,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[ImportManualTimingsService] Segment timings import database update failed: " . $e->getMessage());
            throw new RuntimeException("Segment timings database update failed: " . $e->getMessage(), 0, $e);
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
