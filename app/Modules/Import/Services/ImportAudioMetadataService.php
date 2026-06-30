<?php

namespace App\Modules\Import\Services;

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ImportAudioMetadataService
{
    protected QuranPathResolver $pathResolver;

    public function __construct(QuranPathResolver $pathResolver)
    {
        $this->pathResolver = $pathResolver;
    }

    /**
     * Import audio metadata from local metadata.json files.
     *
     * @param string|null $sourcePath Optional path to a specific metadata file or directory.
     * @return array Standardized report.
     */
    public function import(?string $sourcePath = null): array
    {
        $startTime = microtime(true);

        $report = [
            'source'               => $sourcePath ?? $this->pathResolver->audio(),
            'reciters_imported'    => 0,
            'audio_files_imported' => 0,
            'skipped'              => 0,
            'errors'               => 0,
            'duration_ms'          => 0,
            'imported_at'          => now()->toIso8601String(),
        ];

        $files = $this->resolveFiles($sourcePath);

        if (empty($files)) {
            Log::warning('[ImportAudioMetadata] No metadata files found for import', ['path' => $sourcePath]);
            return $report;
        }

        foreach ($files as $file) {
            DB::beginTransaction();
            try {
                $this->importFile($file, $report);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $report['errors']++;
                Log::error("[ImportAudioMetadata] Failed to import audio metadata file: {$file}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $report['duration_ms'] = (int) round((microtime(true) - $startTime) * 1000);

        Log::info('[ImportAudioMetadata] Complete', $report);

        return $report;
    }

    /**
     * Resolve the list of metadata files.
     */
    private function resolveFiles(?string $path): array
    {
        if ($path && is_file($path)) {
            return [$path];
        }

        $dir = $path ?? $this->pathResolver->audio();
        if (!is_dir($dir)) {
            return [];
        }

        // Support both direct metadata.json file path or nested within reciter folders
        if (file_exists(rtrim($dir, DIRECTORY_SEPARATOR) . '/metadata.json')) {
            return [rtrim($dir, DIRECTORY_SEPARATOR) . '/metadata.json'];
        }

        // Search one level deep for folders containing metadata.json (e.g. yasser-al-dosari/metadata.json)
        $metadataFiles = [];
        $subDirs = glob(rtrim($dir, DIRECTORY_SEPARATOR) . '/*', GLOB_ONLYDIR);
        if ($subDirs) {
            foreach ($subDirs as $subDir) {
                $meta = $subDir . '/metadata.json';
                if (file_exists($meta)) {
                    $metadataFiles[] = $meta;
                }
            }
        }

        return $metadataFiles;
    }

    /**
     * Import a single metadata file.
     */
    private function importFile(string $filePath, array &$report): void
    {
        Log::info("[ImportAudioMetadata] Processing file: {$filePath}");

        $content = file_get_contents($filePath);
        $payload = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in file {$filePath}: " . json_last_error_msg());
        }

        // Validate payload structure
        if (!isset($payload['reciter']) || !is_array($payload['reciter'])) {
            throw new InvalidArgumentException("Missing or invalid 'reciter' block.");
        }

        $reciterData = $payload['reciter'];
        if (empty($reciterData['name_arabic']) || empty($reciterData['name_english']) || empty($reciterData['slug'])) {
            throw new InvalidArgumentException("Reciter requires 'name_arabic', 'name_english', and 'slug'.");
        }

        // Find or create Reciter
        $reciter = Reciter::updateOrCreate(
            ['slug' => $reciterData['slug']],
            [
                'name_arabic'  => $reciterData['name_arabic'],
                'name_english' => $reciterData['name_english'],
                'is_default'   => (bool) ($reciterData['is_default'] ?? false),
            ]
        );

        if ($reciter->wasRecentlyCreated) {
            $report['reciters_imported']++;
            Log::info("[ImportAudioMetadata] Created new reciter: {$reciter->name_english} ({$reciter->slug})");
        }

        if (!isset($payload['audio_files']) || !is_array($payload['audio_files'])) {
            throw new InvalidArgumentException("Missing or invalid 'audio_files' array.");
        }

        $seenSurahs = [];
        foreach ($payload['audio_files'] as $audioFile) {
            $this->validateAndImportAudioFile($reciter, $audioFile, $seenSurahs, $report);
        }
    }

    /**
     * Validate and import a single audio file entry.
     */
    private function validateAndImportAudioFile(Reciter $reciter, array $audioFile, array &$seenSurahs, array &$report): void
    {
        if (!isset($audioFile['surah_number']) || !is_int($audioFile['surah_number'])) {
            throw new InvalidArgumentException("Missing or invalid 'surah_number'.");
        }

        $surahNumber = $audioFile['surah_number'];
        if ($surahNumber < 1 || $surahNumber > 114) {
            throw new InvalidArgumentException("Surah number {$surahNumber} is out of range (1..114).");
        }

        if (empty($audioFile['file_path']) || !is_string($audioFile['file_path'])) {
            throw new InvalidArgumentException("Missing or invalid 'file_path' for Surah {$surahNumber}.");
        }

        $relativeFilePath = $audioFile['file_path'];
        $absoluteFilePath = $this->pathResolver->audio($relativeFilePath);

        if (!file_exists($absoluteFilePath)) {
            throw new RuntimeException("Audio file does not exist on disk: {$absoluteFilePath}");
        }

        // Duplicate checks
        if (in_array($surahNumber, $seenSurahs, true)) {
            throw new InvalidArgumentException("Duplicate entry for surah_number {$surahNumber} in metadata.");
        }
        $seenSurahs[] = $surahNumber;

        // Auto-resolve duration and format using ffprobe if missing
        $durationMs = $audioFile['duration_ms'] ?? null;
        $format = $audioFile['format'] ?? null;

        if ($durationMs === null || $format === null) {
            Log::info("[ImportAudioMetadata] Probe missing duration/format for Surah {$surahNumber}");
            $probed = $this->probeAudioFile($absoluteFilePath);
            $durationMs = $durationMs ?? $probed['duration_ms'];
            $format = $format ?? $probed['format'];
        }

        // Save
        $record = AudioFile::updateOrCreate(
            [
                'reciter_id'   => $reciter->id,
                'surah_number' => $surahNumber,
            ],
            [
                'file_path'   => $relativeFilePath,
                'duration_ms' => $durationMs,
                'format'      => $format,
                'file_size'   => filesize($absoluteFilePath),
            ]
        );

        $report['audio_files_imported']++;
        Log::debug("[ImportAudioMetadata] Imported AudioFile record: ID={$record->id}, Surah={$surahNumber}");
    }

    /**
     * Run ffprobe to resolve duration and format.
     */
    private function probeAudioFile(string $filePath): array
    {
        $ffprobe = config('ffmpeg.ffprobe_path', 'ffprobe');
        
        $cmd = sprintf(
            '"%s" -v error -show_entries format=duration,format_name -of default=noprint_wrappers=1:nokey=1 "%s"',
            $ffprobe,
            $filePath
        );

        Log::debug("[ImportAudioMetadata] Running ffprobe command", ['cmd' => $cmd]);

        $output = [];
        $resultCode = -1;
        exec($cmd . ' 2>&1', $output, $resultCode);

        if ($resultCode !== 0 || count($output) < 2) {
            $errorOutput = implode("\n", $output);
            Log::error("[ImportAudioMetadata] ffprobe failed", [
                'result_code' => $resultCode,
                'output'      => $errorOutput,
            ]);
            throw new RuntimeException("ffprobe failed. Exit code {$resultCode}. Output:\n{$errorOutput}");
        }

        // Expected output:
        // Line 1: format name (e.g. mp3)
        // Line 2: duration in seconds (e.g. 12.633000)
        $format = trim($output[0]);
        $durationSeconds = (float) trim($output[1]);
        $durationMs = (int) round($durationSeconds * 1000);

        return [
            'duration_ms' => $durationMs,
            'format'      => $format,
        ];
    }
}
