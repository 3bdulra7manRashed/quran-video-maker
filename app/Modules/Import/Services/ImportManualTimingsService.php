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

        // Fetch approved segments using the canonical ApprovedContentResolver or incoming payload
        $fromAyah = isset($data['from_ayah']) ? (int) $data['from_ayah'] : null;
        $toAyah = isset($data['to_ayah']) ? (int) $data['to_ayah'] : null;
        $resolver = app(\App\Services\ContentGeneration\ApprovedContentResolver::class);

        // Prioritize segments provided directly in incoming payload if they contain Quranic Arabic text
        $hasSegmentArabic = false;
        if (!empty($jsonSegments)) {
            foreach ($jsonSegments as $js) {
                if (!empty($js['arabic'])) {
                    $hasSegmentArabic = true;
                    break;
                }
            }
        }

        if ($hasSegmentArabic) {
            $collection = collect();
            foreach ($jsonSegments as $seg) {
                $record = new \App\Modules\Quran\Models\ReelsGeneratedContent();
                $record->reciter_id = $reciterId;
                $record->surah_number = $surahNumber;
                $record->start_ayah = $fromAyah ?? 1;
                $record->end_ayah = $toAyah ?? 999;
                $record->layout_type = \App\Enums\LayoutType::REELS;
                $record->segment_order = (int)($seg['order'] ?? $seg['segment_order'] ?? 1);
                $record->arabic = $seg['arabic'] ?? '';
                $record->translation = $seg['translation'] ?? '';
                $record->tafsir = $seg['tafsir'] ?? '';
                $record->approval_status = \App\Enums\ContentApprovalStatus::APPROVED;
                $collection->push($record);
            }
            $approvedSegments = $collection;
        } else {
            // Otherwise resolve pre-existing approved segments from DB
            $approvedSegments = $resolver->resolve($reciterId, $surahNumber, $fromAyah, $toAyah, 'reels');

            // Overlay any custom tafsir or translation supplied in incoming jsonSegments
            if ($approvedSegments !== null && !$approvedSegments->isEmpty() && !empty($jsonSegments)) {
                $jsonSegByOrder = collect($jsonSegments)->keyBy(fn($s) => (int)($s['order'] ?? $s['segment_order'] ?? 1));
                foreach ($approvedSegments as $seg) {
                    $js = $jsonSegByOrder->get($seg->segment_order);
                    if ($js) {
                        if (array_key_exists('tafsir', $js)) {
                            $seg->tafsir = $js['tafsir'];
                        }
                        if (array_key_exists('translation', $js)) {
                            $seg->translation = $js['translation'];
                        }
                    }
                }
            }
        }

        if ($approvedSegments === null || $approvedSegments->isEmpty()) {
            throw new \RuntimeException("No approved segment definitions found for this Surah range. Please generate and approve segments first.");
        }

        // Fetch Surah & Words
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new \InvalidArgumentException("Surah {$surahNumber} not found.");
        }

        $ayahQuery = $surah->ayahs();
        if ($fromAyah !== null) {
            $ayahQuery->where('ayah_number', '>=', $fromAyah);
        }
        if ($toAyah !== null) {
            $ayahQuery->where('ayah_number', '<=', $toAyah);
        }
        $ayahIds = $ayahQuery->pluck('id');

        $words = Word::with('ayah')->whereIn('ayah_id', $ayahIds)
            ->orderBy('page_number')
            ->orderBy('line_number')
            ->orderBy('ayah_id')
            ->orderBy('word_index')
            ->get()
            ->all();

        // Instrumenting diagnostics logs for Segment #1 alignment failure
        \Illuminate\Support\Facades\Log::info('ALIGNMENT_DIAGNOSTICS_START');
        
        // 8. ApprovedContentResolver info
        \Illuminate\Support\Facades\Log::info('Resolver Check', [
            'resolver_class' => get_class($resolver),
            'collection_count' => $approvedSegments ? $approvedSegments->count() : 0,
            'returned_ids' => $approvedSegments ? $approvedSegments->pluck('id')->toArray() : [],
        ]);

        // 1. Total approved segments loaded
        \Illuminate\Support\Facades\Log::info('Total segments count', [
            'count' => $approvedSegments ? $approvedSegments->count() : 0,
        ]);

        // 2. For every loaded segment
        if ($approvedSegments) {
            foreach ($approvedSegments as $idx => $s) {
                $layout = is_object($s->layout_type) ? $s->layout_type->value : $s->layout_type;
                $status = is_object($s->approval_status) ? $s->approval_status->value : $s->approval_status;
                \Illuminate\Support\Facades\Log::info("Loaded Segment details #{$idx}", [
                    'id' => $s->id,
                    'segment_order' => $s->segment_order,
                    'approval_status' => $status,
                    'layout_type' => $layout,
                    'start_ayah' => $s->start_ayah,
                    'end_ayah' => $s->end_ayah,
                    'arabic_text_first_80' => mb_substr($s->arabic, 0, 80),
                ]);
            }
        }

        // 3. Verify whether there is more than one Segment #1
        if ($approvedSegments) {
            $seg1Count = $approvedSegments->filter(fn($s) => $s->segment_order == 1)->count();
            \Illuminate\Support\Facades\Log::info("Verify Segment #1 count", [
                'more_than_one_segment_1' => $seg1Count > 1 ? 'YES' : 'NO',
                'actual_segment_1_count' => $seg1Count,
            ]);
        }

        // 4. Exact SQL used to load segments
        $fromAyahVal = $fromAyah ?? 1;
        $endAyahVal = $toAyah;
        if ($endAyahVal === null) {
            $endAyahVal = $surah ? $surah->verses_count : 114;
        }
        $mockQuery = \App\Modules\Quran\Models\ReelsGeneratedContent::where('reciter_id', $reciterId)
            ->where('surah_number', $surahNumber)
            ->where('start_ayah', $fromAyahVal)
            ->where('end_ayah', $endAyahVal)
            ->where('layout_type', 'reels')
            ->where('approval_status', \App\Enums\ContentApprovalStatus::APPROVED)
            ->orderBy('segment_order');
        \Illuminate\Support\Facades\Log::info("Exact SQL executed", [
            'sql' => $mockQuery->toSql(),
            'bindings' => $mockQuery->getBindings(),
            'result_count' => $approvedSegments ? $approvedSegments->count() : 0,
        ]);

        // 5. Log the first 20 database words passed into align()
        $first20WordsInfo = [];
        foreach (array_slice($words, 0, 20) as $idx => $w) {
            $first20WordsInfo[] = [
                'index' => $idx,
                'id' => $w->id,
                'char_type' => $w->char_type,
                'uthmani_text' => $w->uthmani_text,
                'normalized' => \App\Services\ContentGeneration\ContentNormalizer::normalizeForMatching($w->uthmani_text),
            ];
        }
        \Illuminate\Support\Facades\Log::info("First 20 DB words passed into align()", $first20WordsInfo);

        // 6. Log the normalized text of Segment #1 and database words consumed
        if ($approvedSegments && !$approvedSegments->isEmpty()) {
            $seg1 = $approvedSegments->first();
            $seg1Norm = \App\Services\ContentGeneration\ContentNormalizer::normalizeForMatching($seg1->arabic);
            $seg1NormClean = preg_replace('/\s+/u', '', $seg1Norm);

            // Accumulate first 10 spoken words' normalized form
            $accWords = [];
            $accWordsText = "";
            foreach ($words as $w) {
                if ($w->char_type === 'word') {
                    $wNorm = \App\Services\ContentGeneration\ContentNormalizer::normalizeForMatching($w->uthmani_text);
                    $wClean = preg_replace('/\s+/u', '', $wNorm);
                    if ($wClean !== '') {
                        $accWords[] = $w->uthmani_text;
                        $accWordsText .= $wClean;
                        if (mb_strlen($accWordsText) >= mb_strlen($seg1NormClean)) {
                            break;
                        }
                    }
                }
            }
            \Illuminate\Support\Facades\Log::info("Normalized timing texts compared", [
                'expected_seg1_normalized' => $seg1NormClean,
                'database_spoken_words' => $accWords,
                'database_accumulated_normalized' => $accWordsText,
            ]);
        }

        // Align segments with database words
        $aligner = app(\App\Services\ContentGeneration\GeneratedContentWordAligner::class);
        try {
            $alignedRanges = $aligner->align($approvedSegments, $words);
        } catch (\Throwable $e) {
            // 7. Print the exact point where align() throws AlignmentException
            \Illuminate\Support\Facades\Log::error("Alignment failed with error", [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }

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
                $endMs = $nextStart !== null 
                    ? $nextStart 
                    : (isset($data['end_time_ms']) 
                        ? (int)$data['end_time_ms'] 
                        : ($totalDurationMs !== null ? $totalDurationMs : ($startMs + 5000)));

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

            // If persist_to_database is requested, cleanly archive existing records and save new template version
            if (!empty($data['persist_to_database']) || !empty($data['save_to_database'])) {
                $startAyahVal = $fromAyah ? (int)$fromAyah : 1;
                $endAyahVal = $toAyah ? (int)$toAyah : ($surah ? $surah->verses_count : 114);
                $layoutType = \App\Enums\LayoutType::REELS;

                $maxVersion = \App\Modules\Quran\Models\ReelsGeneratedContent::where('reciter_id', $reciterId)
                    ->where('surah_number', $surahNumber)
                    ->where('start_ayah', $startAyahVal)
                    ->where('end_ayah', $endAyahVal)
                    ->where('layout_type', $layoutType)
                    ->max('content_version') ?? 0;

                $newVersion = $maxVersion + 1;

                // Archive all currently approved entries for this key
                \App\Modules\Quran\Models\ReelsGeneratedContent::where('reciter_id', $reciterId)
                    ->where('surah_number', $surahNumber)
                    ->where('start_ayah', $startAyahVal)
                    ->where('end_ayah', $endAyahVal)
                    ->where('layout_type', $layoutType)
                    ->where('approval_status', \App\Enums\ContentApprovalStatus::APPROVED)
                    ->update(['approval_status' => \App\Enums\ContentApprovalStatus::ARCHIVED]);

                // Save new approved entries
                $jsonSegByOrder = collect($jsonSegments)->keyBy(fn($s) => (int)($s['order'] ?? $s['segment_order'] ?? 1));
                foreach ($approvedSegments as $appSeg) {
                    $order = (int)$appSeg->segment_order;
                    $js = $jsonSegByOrder->get($order);
                    $arabicText = (!empty($js) && !empty($js['arabic'])) ? $js['arabic'] : $appSeg->arabic;
                    $transText = (!empty($js) && array_key_exists('translation', $js)) ? $js['translation'] : ($appSeg->translation ?? '');
                    $tafsirText = (!empty($js) && array_key_exists('tafsir', $js)) ? $js['tafsir'] : ($appSeg->tafsir ?? '');

                    if (!empty($arabicText)) {
                        \App\Modules\Quran\Models\ReelsGeneratedContent::create([
                            'reciter_id' => $reciterId,
                            'surah_number' => $surahNumber,
                            'start_ayah' => $startAyahVal,
                            'end_ayah' => $endAyahVal,
                            'layout_type' => $layoutType,
                            'segment_order' => $order,
                            'arabic' => \App\Services\ContentGeneration\ContentNormalizer::normalizeArabic($arabicText),
                            'translation' => \App\Services\ContentGeneration\ContentNormalizer::normalizeTranslation($transText ?? ''),
                            'tafsir' => \App\Services\ContentGeneration\ContentNormalizer::normalizeTafsir($tafsirText ?? ''),
                            'approval_status' => \App\Enums\ContentApprovalStatus::APPROVED,
                            'content_version' => $newVersion,
                            'generator_type' => \App\Enums\GeneratorType::MANUAL,
                            'source_json' => $data,
                            'generated_at' => now(),
                        ]);
                    }
                }
            }

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
