<?php

namespace App\Modules\Import\Services;

use App\Modules\Quran\Models\Ayah;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\ReciterWordTiming;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ImportWordTimingsService
{
    /**
     * Import word timings from local JSON files.
     *
     * @param string|null $sourcePath Optional path to a specific file or directory.
     * @param int|null $reciterId The target reciter ID (resolves default if null).
     * @return array Standardized report.
     */
    public function import(?string $sourcePath = null, ?int $reciterId = null): array
    {
        $startTime = microtime(true);

        if ($reciterId === null) {
            $defaultReciter = Reciter::where('is_default', true)->first();
            if (!$defaultReciter) {
                throw new RuntimeException("Default reciter not found in database.");
            }
            $reciterId = $defaultReciter->id;
        }

        $report = [
            'source'            => $sourcePath ?? storage_path('app/quran/timings/'),
            'files_processed'   => 0,
            'ayahs_processed'   => 0,
            'timings_imported'  => 0,
            'skipped'           => 0,
            'errors'            => 0,
            'duration_ms'       => 0,
            'imported_at'       => now()->toIso8601String(),
        ];

        $files = $this->resolveFiles($sourcePath);

        if (empty($files)) {
            Log::warning('[ImportWordTimings] No timing files found for import', ['path' => $sourcePath]);
            return $report;
        }

        foreach ($files as $file) {
            DB::beginTransaction();
            try {
                $this->importFile($file, $report, $reciterId);
                DB::commit();
                $report['files_processed']++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $report['errors']++;
                Log::error("[ImportWordTimings] Failed to import timing file: {$file}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $report['duration_ms'] = (int) round((microtime(true) - $startTime) * 1000);

        Log::info('[ImportWordTimings] Complete', $report);

        return $report;
    }

    /**
     * Resolve the list of timing files.
     */
    private function resolveFiles(?string $path): array
    {
        if ($path && is_file($path)) {
            return [$path];
        }

        $dir = $path ?? storage_path('app/quran/timings/');
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) . '/timings_*_*.json');
        if ($files === false) {
            return [];
        }

        natsort($files);

        return array_values($files);
    }

    /**
     * Import timings from a single file.
     */
    private function importFile(string $filePath, array &$report, int $reciterId): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $filename = basename($filePath);
        if (!preg_match('/timings_(\d+)_(\d+)\.json$/', $filename, $matches)) {
            throw new InvalidArgumentException("Filename does not match timings_SURAH_AYAH.json: {$filename}");
        }

        $surahNumber = (int) $matches[1];
        $ayahNumber  = (int) $matches[2];
        $verseKey    = "{$surahNumber}:{$ayahNumber}";

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        $timings = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in file {$filename}: " . json_last_error_msg());
        }

        if (!is_array($timings) || empty($timings)) {
            throw new InvalidArgumentException("Timings JSON must contain a non-empty array of segment entries.");
        }

        $ayah = Ayah::where('verse_key', $verseKey)->first();
        if (!$ayah) {
            throw new InvalidArgumentException("Ayah not found in database for verse_key: {$verseKey}");
        }

        $report['ayahs_processed']++;

        $spokenWords = Word::where('ayah_id', $ayah->id)
            ->where('char_type', 'word')
            ->orderBy('word_index')
            ->get();

        $this->validateTimings($verseKey, $timings, $spokenWords);

        // Check if DB timings are already matching file timings exactly
        $existingRecords = ReciterWordTiming::where('reciter_id', $reciterId)
            ->whereIn('word_id', $spokenWords->pluck('id'))
            ->get()
            ->keyBy('word_id');

        $alreadyMatches = true;
        foreach ($timings as $index => $entry) {
            [, $startMs, $endMs] = $entry;
            $word = $spokenWords[$index];
            $existing = $existingRecords->get($word->id);
            if (!$existing || $existing->start_ms !== $startMs || $existing->end_ms !== $endMs) {
                $alreadyMatches = false;
                break;
            }
        }

        if ($alreadyMatches) {
            $report['skipped']++;
            Log::info("[ImportWordTimings] Timings already match for verse {$verseKey}, skipping database write.");
            return;
        }

        // Perform upsert into reciter_word_timings
        $upsertData = [];
        foreach ($timings as $index => $entry) {
            [, $startMs, $endMs] = $entry;
            $word = $spokenWords[$index];
            $upsertData[] = [
                'reciter_id' => $reciterId,
                'word_id' => $word->id,
                'start_ms' => $startMs,
                'end_ms' => $endMs,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ReciterWordTiming::upsert(
            $upsertData,
            ['reciter_id', 'word_id'],
            ['start_ms', 'end_ms', 'updated_at']
        );

        $report['timings_imported'] += count($timings);

        Log::info("[ImportWordTimings] Successfully imported timings for verse {$verseKey}", [
            'entries' => count($timings),
        ]);
    }

    /**
     * Validate timing entries count, sequence, bounds, and continuity.
     */
    private function validateTimings(string $verseKey, array $timings, $spokenWords): void
    {
        $dbSpokenCount = $spokenWords->count();
        $fileSpokenCount = count($timings);

        if ($dbSpokenCount !== $fileSpokenCount) {
            throw new InvalidArgumentException(
                "Spoken word count mismatch for verse {$verseKey}: Database has {$dbSpokenCount} spoken words, but timings file contains {$fileSpokenCount} entries."
            );
        }

        $previousEndMs = 0;
        foreach ($timings as $index => $entry) {
            if (!is_array($entry) || count($entry) !== 3) {
                throw new InvalidArgumentException(
                    "Invalid timing entry format at index {$index} for verse {$verseKey}: expected exactly [word_index, start_ms, end_ms]."
                );
            }

            [$wordIndex, $startMs, $endMs] = $entry;

            if (!is_int($wordIndex) || !is_int($startMs) || !is_int($endMs)) {
                throw new InvalidArgumentException(
                    "Invalid non-integer value in timing entry at index {$index} for verse {$verseKey}."
                );
            }

            $expectedWordIndex = $index + 1;
            if ($wordIndex !== $expectedWordIndex) {
                throw new InvalidArgumentException(
                    "Out-of-order word_index at index {$index} for verse {$verseKey}: expected {$expectedWordIndex}, got {$wordIndex}."
                );
            }

            if ($startMs < 0) {
                throw new InvalidArgumentException(
                    "Negative start_ms ({$startMs}) at index {$index} for verse {$verseKey}."
                );
            }

            if ($endMs <= $startMs) {
                throw new InvalidArgumentException(
                    "End_ms ({$endMs}) is not strictly greater than start_ms ({$startMs}) at index {$index} for verse {$verseKey}."
                );
            }

            // Continuity check
            if ($index > 0 && $startMs < $previousEndMs) {
                throw new InvalidArgumentException(
                    "Timeline continuity broken in verse {$verseKey}: entry {$expectedWordIndex} start_ms ({$startMs}) is less than previous end_ms ({$previousEndMs})."
                );
            }

            $previousEndMs = $endMs;
        }
    }
}
