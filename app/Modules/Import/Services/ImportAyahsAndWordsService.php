<?php

namespace App\Modules\Import\Services;

use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Ayah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ImportAyahsAndWordsService
{
    private const EXPECTED_WORD_CHAR_TYPES = ['word', 'end', 'pause'];

    /**
     * Import Ayahs and Words from local JSON files.
     *
     * @param string|null $sourcePath Optional path to a specific file or directory.
     * @return array Standardized report.
     */
    public function import(?string $sourcePath = null): array
    {
        $startTime = microtime(true);

        $report = [
            'source'            => $sourcePath ?? storage_path('app/quran/glyph/'),
            'files_processed'   => 0,
            'surahs_processed'  => 0,
            'ayahs_imported'    => 0,
            'words_imported'    => 0,
            'skipped'           => 0,
            'errors'            => 0,
            'duration_ms'       => 0,
            'imported_at'       => now()->toIso8601String(),
        ];

        $files = $this->resolveFiles($sourcePath);

        if (empty($files)) {
            Log::warning('[ImportAyahsAndWords] No files found for import', ['path' => $sourcePath]);
            return $report;
        }

        foreach ($files as $file) {
            DB::beginTransaction();
            try {
                $this->importFile($file, $report);
                DB::commit();
                $report['files_processed']++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $report['errors']++;
                Log::error("[ImportAyahsAndWords] Failed to import file: {$file}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $report['duration_ms'] = (int) round((microtime(true) - $startTime) * 1000);

        Log::info('[ImportAyahsAndWords] Complete', $report);

        return $report;
    }

    /**
     * Resolve the list of files to import.
     */
    private function resolveFiles(?string $path): array
    {
        if ($path && is_file($path)) {
            return [$path];
        }

        $dir = $path ?? storage_path('app/quran/glyph/');
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) . '/surah_*.json');
        if ($files === false) {
            return [];
        }

        // Sort naturally to process surah_1.json, surah_2.json ... surah_10.json in correct numerical order
        natsort($files);

        return array_values($files);
    }

    /**
     * Import a single surah file.
     */
    private function importFile(string $filePath, array &$report): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $filename = basename($filePath);
        if (!preg_match('/surah_(\d+)\.json$/', $filename, $matches)) {
            throw new InvalidArgumentException("Filename does not match expected pattern surah_N.json: {$filename}");
        }

        $surahNumber = (int) $matches[1];
        $surah = Surah::where('number', $surahNumber)->first();

        if (!$surah) {
            throw new InvalidArgumentException("Surah number {$surahNumber} from file name not found in database.");
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        $data = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON: " . json_last_error_msg());
        }

        if (!isset($data['verses']) || !is_array($data['verses'])) {
            throw new InvalidArgumentException("Missing 'verses' root key in JSON.");
        }

        $report['surahs_processed']++;

        foreach ($data['verses'] as $verse) {
            $this->importVerse($surah, $verse, $report);
        }
    }

    /**
     * Validate and import a single verse (Ayah) and its words.
     */
    private function importVerse(Surah $surah, array $verse, array &$report): void
    {
        $this->validateVerse($surah, $verse);

        $ayah = Ayah::firstOrCreate(
            ['verse_key' => $verse['verse_key']],
            [
                'surah_id'    => $surah->id,
                'ayah_number' => $verse['verse_number'],
                'page_number' => $verse['page_number'],
                'juz_number'  => $verse['juz_number'],
                'hizb_number' => $verse['hizb_number'],
            ]
        );

        if (!$ayah->wasRecentlyCreated) {
            $report['skipped']++;
        } else {
            $report['ayahs_imported']++;
        }

        $wordsToInsert = [];
        foreach ($verse['words'] as $w) {
            $wordsToInsert[] = [
                'ayah_id'             => $ayah->id,
                'word_index'          => $w['position'],
                'char_type'           => $w['char_type_name'],
                'plain_text'          => $w['text_imlaei'] ?? $w['text_uthmani'] ?? $w['code_v1'],
                'uthmani_text'        => $w['text_uthmani'] ?? null,
                'glyph_text'          => $w['code_v1'],
                'page_number'         => $w['page_number'],
                'line_number'         => $w['line_number'] ?? null,
                'translation_en'      => $w['translation']['text'] ?? null,
                'start_ms_from_surah' => null,
                'end_ms_from_surah'   => null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ];
        }

        if (!empty($wordsToInsert)) {
            DB::table('words')->upsert(
                $wordsToInsert,
                ['ayah_id', 'word_index'],
                ['char_type', 'plain_text', 'uthmani_text', 'glyph_text', 'page_number', 'line_number', 'translation_en', 'updated_at']
            );
            $report['words_imported'] += count($wordsToInsert);
        }
    }

    /**
     * Validate the structural integrity of a single verse record and its words.
     */
    private function validateVerse(Surah $surah, array $verse): void
    {
        if (empty($verse['verse_key']) || !is_string($verse['verse_key'])) {
            throw new InvalidArgumentException("Missing or invalid 'verse_key'.");
        }

        if (!preg_match('/^(\d+):(\d+)$/', $verse['verse_key'], $keyMatches)) {
            throw new InvalidArgumentException("Invalid 'verse_key' format: {$verse['verse_key']}.");
        }

        $keySurahNumber = (int) $keyMatches[1];
        if ($keySurahNumber !== $surah->number) {
            throw new InvalidArgumentException("Verse key {$verse['verse_key']} does not match Surah number {$surah->number}.");
        }

        if (!isset($verse['verse_number']) || !is_int($verse['verse_number']) || $verse['verse_number'] <= 0) {
            throw new InvalidArgumentException("Missing or invalid 'verse_number' in verse {$verse['verse_key']}.");
        }

        if (!isset($verse['page_number']) || !is_int($verse['page_number']) || $verse['page_number'] <= 0) {
            throw new InvalidArgumentException("Missing or invalid 'page_number' in verse {$verse['verse_key']}.");
        }

        if (!isset($verse['juz_number']) || !is_int($verse['juz_number']) || $verse['juz_number'] <= 0) {
            throw new InvalidArgumentException("Missing or invalid 'juz_number' in verse {$verse['verse_key']}.");
        }

        if (!isset($verse['hizb_number']) || !is_int($verse['hizb_number']) || $verse['hizb_number'] <= 0) {
            throw new InvalidArgumentException("Missing or invalid 'hizb_number' in verse {$verse['verse_key']}.");
        }

        if (empty($verse['words']) || !is_array($verse['words'])) {
            throw new InvalidArgumentException("Missing or empty 'words' array in verse {$verse['verse_key']}.");
        }

        $expectedIndex = 1;
        foreach ($verse['words'] as $index => $w) {
            if (!isset($w['position']) || !is_int($w['position'])) {
                throw new InvalidArgumentException("Missing or invalid word 'position' at index {$index} in verse {$verse['verse_key']}.");
            }

            if ($w['position'] !== $expectedIndex) {
                throw new InvalidArgumentException(
                    "Out-of-order word position in verse {$verse['verse_key']}: expected {$expectedIndex}, got {$w['position']}."
                );
            }

            if (empty($w['char_type_name']) || !in_array($w['char_type_name'], self::EXPECTED_WORD_CHAR_TYPES, true)) {
                throw new InvalidArgumentException(
                    "Missing or invalid 'char_type_name' in verse {$verse['verse_key']} at position {$w['position']}."
                );
            }

            if (empty($w['code_v1']) || !is_string($w['code_v1'])) {
                throw new InvalidArgumentException(
                    "Missing or invalid 'code_v1' in verse {$verse['verse_key']} at position {$w['position']}."
                );
            }

            if (!isset($w['page_number']) || !is_int($w['page_number']) || $w['page_number'] <= 0) {
                throw new InvalidArgumentException(
                    "Missing or invalid word 'page_number' in verse {$verse['verse_key']} at position {$w['position']}."
                );
            }

            if (isset($w['line_number']) && (!is_int($w['line_number']) || $w['line_number'] <= 0)) {
                throw new InvalidArgumentException(
                    "Invalid word 'line_number' in verse {$verse['verse_key']} at position {$w['position']}."
                );
            }

            $expectedIndex++;
        }
    }
}
