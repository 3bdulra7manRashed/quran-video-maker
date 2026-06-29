<?php

namespace App\Modules\Import\Services;

use App\Modules\Quran\Models\Surah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ImportSurahsService
{
    private const EXPECTED_SURAH_COUNT = 114;
    private const MIN_PAGE = 1;
    private const MAX_PAGE = 604;

    /**
     * Import Surahs from a local JSON file.
     *
     * @param string $filePath Absolute path to the chapters JSON file.
     * @return array Import report with counts and errors.
     *
     * @throws RuntimeException If the file cannot be read or parsed.
     * @throws InvalidArgumentException If the data structure is invalid.
     */
    public function import(string $filePath): array
    {
        $startTime = microtime(true);
        $report = [
            'source'      => $filePath,
            'total'       => 0,
            'imported'    => 0,
            'skipped'     => 0,
            'errors'      => 0,
            'duration_ms' => 0,
            'imported_at' => now()->toIso8601String(),
        ];

        Log::info('[ImportSurahs] Starting import', ['file' => $filePath]);

        $data = $this->loadAndValidateFile($filePath);
        $chapters = $data['chapters'];
        $report['total'] = count($chapters);

        $this->validateChapterCount($chapters);

        DB::beginTransaction();

        try {
            foreach ($chapters as $chapter) {
                $this->importSingleSurah($chapter, $report);
            }

            DB::commit();

            $report['duration_ms'] = (int) round((microtime(true) - $startTime) * 1000);

            Log::info('[ImportSurahs] Import completed', [
                'imported' => $report['imported'],
                'skipped'  => $report['skipped'],
                'errors'   => $report['errors'],
                'duration' => $report['duration_ms'] . 'ms',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[ImportSurahs] Import failed, transaction rolled back', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $report;
    }

    /**
     * Load and validate the JSON source file.
     */
    private function loadAndValidateFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Source file not found: {$filePath}");
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new RuntimeException("Failed to read source file: {$filePath}");
        }

        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }

        if (!isset($data['chapters']) || !is_array($data['chapters'])) {
            throw new InvalidArgumentException('Missing or invalid "chapters" key in source file.');
        }

        return $data;
    }

    /**
     * Validate that we have exactly 114 chapters.
     */
    private function validateChapterCount(array $chapters): void
    {
        $count = count($chapters);

        if ($count !== self::EXPECTED_SURAH_COUNT) {
            throw new InvalidArgumentException(
                "Expected " . self::EXPECTED_SURAH_COUNT . " chapters, found {$count}."
            );
        }
    }

    private function importSingleSurah(array $chapter, array &$report): void
    {
        $errors = $this->validateChapter($chapter);

        if (!empty($errors)) {
            $report['errors']++;
            Log::warning('[ImportSurahs] Validation failed for chapter', [
                'chapter' => $chapter['id'] ?? 'unknown',
                'issues'  => $errors,
            ]);
            throw new InvalidArgumentException(
                "Validation failed for chapter {$chapter['id']}: " . implode(', ', $errors)
            );
        }

        $existing = Surah::where('number', $chapter['id'])->first();

        if ($existing) {
            $report['skipped']++;
            Log::info('[ImportSurahs] Surah already exists, skipping', [
                'number' => $chapter['id'],
            ]);
            return;
        }

        Surah::create([
            'number'       => $chapter['id'],
            'name_arabic'  => $chapter['name_arabic'],
            'name_simple'  => $chapter['name_simple'],
            'surah_glyph'  => null,
            'verses_count' => $chapter['verses_count'],
            'start_page'   => $chapter['pages'][0],
            'end_page'     => $chapter['pages'][1],
        ]);

        $report['imported']++;

        Log::info('[ImportSurahs] Imported surah', [
            'number'     => $chapter['id'],
            'name'       => $chapter['name_simple'],
            'verses'     => $chapter['verses_count'],
            'pages'      => $chapter['pages'][0] . '-' . $chapter['pages'][1],
        ]);
    }

    /**
     * Validate a single chapter record.
     *
     * @return string[] List of validation error messages.
     */
    private function validateChapter(array $chapter): array
    {
        $errors = [];

        if (!isset($chapter['id']) || !is_int($chapter['id'])) {
            $errors[] = 'Missing or invalid "id".';
        } elseif ($chapter['id'] < 1 || $chapter['id'] > self::EXPECTED_SURAH_COUNT) {
            $errors[] = "\"id\" out of range: {$chapter['id']}.";
        }

        if (empty($chapter['name_arabic'])) {
            $errors[] = 'Missing "name_arabic".';
        }

        if (empty($chapter['name_simple'])) {
            $errors[] = 'Missing "name_simple".';
        }

        if (!isset($chapter['verses_count']) || !is_int($chapter['verses_count']) || $chapter['verses_count'] < 1) {
            $errors[] = 'Missing or invalid "verses_count".';
        }

        if (!isset($chapter['pages']) || !is_array($chapter['pages']) || count($chapter['pages']) !== 2) {
            $errors[] = 'Missing or invalid "pages" array.';
        } else {
            $startPage = $chapter['pages'][0];
            $endPage   = $chapter['pages'][1];

            if (!is_int($startPage) || $startPage < self::MIN_PAGE || $startPage > self::MAX_PAGE) {
                $errors[] = "Invalid start_page: {$startPage}.";
            }

            if (!is_int($endPage) || $endPage < self::MIN_PAGE || $endPage > self::MAX_PAGE) {
                $errors[] = "Invalid end_page: {$endPage}.";
            }

            if (is_int($startPage) && is_int($endPage) && $startPage > $endPage) {
                $errors[] = "start_page ({$startPage}) > end_page ({$endPage}).";
            }
        }

        return $errors;
    }
}
