<?php

namespace App\Modules\Import\Services;

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\AudioFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ImportAudioMetadataService
{
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
            'source'               => $sourcePath ?? storage_path('app/quran/audio/'),
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

        $dir = $path ?? storage_path('app/quran/audio/');
        if (!is_dir($dir)) {
            return [];
        }

        // Support both direct metadata.json file path or nested within reciter folders
        if (file_exists(rtrim($dir, DIRECTORY_SEPARATOR) . '/metadata.json')) {
            return [rtrim($dir, DIRECTORY_SEPARATOR) . '/metadata.json'];
        }

        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) . '/*/metadata.json');
        if ($files === false) {
            return [];
        }

        natsort($files);

        return array_values($files);
    }

    /**
     * Import a single metadata file.
     */
    private function importFile(string $filePath, array &$report): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        $data = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON: " . json_last_error_msg());
        }

        // Validate structure
        if (empty($data['reciter']) || !is_array($data['reciter'])) {
            throw new InvalidArgumentException("Missing or invalid 'reciter' block.");
        }

        $reciterData = $data['reciter'];
        if (empty($reciterData['name_arabic']) || empty($reciterData['name_english']) || empty($reciterData['slug'])) {
            throw new InvalidArgumentException("Reciter block missing required fields: name_arabic, name_english, slug.");
        }

        if (empty($data['audio_files']) || !is_array($data['audio_files'])) {
            throw new InvalidArgumentException("Missing or empty 'audio_files' array.");
        }

        // Create or update reciter
        $existingReciter = Reciter::where('slug', $reciterData['slug'])->first();
        
        $reciter = Reciter::updateOrCreate(
            ['slug' => $reciterData['slug']],
            [
                'name_arabic'  => $reciterData['name_arabic'],
                'name_english' => $reciterData['name_english'],
                'is_default'   => (bool) ($reciterData['is_default'] ?? false),
            ]
        );

        if (!$existingReciter) {
            $report['reciters_imported']++;
        }

        $seenSurahs = [];
        $seenPaths = [];

        foreach ($data['audio_files'] as $audioFile) {
            $this->importSingleAudioFile($reciter, $audioFile, dirname($filePath), $seenSurahs, $seenPaths, $report);
        }
    }

    /**
     * Validate and import a single audio file.
     */
    private function importSingleAudioFile(
        Reciter $reciter,
        array $audioFile,
        string $baseDir,
        array &$seenSurahs,
        array &$seenPaths,
        array &$report
    ): void {
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
        $absoluteFilePath = storage_path('app/quran/audio/' . $relativeFilePath);

        if (!file_exists($absoluteFilePath)) {
            throw new RuntimeException("Audio file does not exist on disk: {$absoluteFilePath}");
        }

        // Duplicate checks
        if (in_array($surahNumber, $seenSurahs, true)) {
            throw new InvalidArgumentException("Duplicate entry for surah_number {$surahNumber} in metadata.");
        }
        $seenSurahs[] = $surahNumber;

        if (in_array($relativeFilePath, $seenPaths, true)) {
            throw new InvalidArgumentException("Duplicate file_path '{$relativeFilePath}' in metadata.");
        }
        $seenPaths[] = $relativeFilePath;

        // Resolve duration
        $durationMs = isset($audioFile['duration_ms']) ? (int) $audioFile['duration_ms'] : 0;
        if ($durationMs <= 0) {
            // Fall back to FFprobe
            $durationMs = $this->getAudioDurationMs($absoluteFilePath);
        }

        if ($durationMs <= 0) {
            throw new InvalidArgumentException("Invalid duration resolved for Surah {$surahNumber}. Must be greater than 0.");
        }

        // Idempotency check: see if database matches exactly
        $existing = AudioFile::where('reciter_id', $reciter->id)
            ->where('surah_number', $surahNumber)
            ->first();

        if ($existing) {
            if ($existing->file_path === $relativeFilePath && $existing->duration_ms === $durationMs) {
                $report['skipped']++;
                return;
            }
        }

        AudioFile::updateOrCreate(
            [
                'reciter_id'   => $reciter->id,
                'surah_number' => $surahNumber,
            ],
            [
                'file_path'   => $relativeFilePath,
                'duration_ms' => $durationMs,
            ]
        );

        $report['audio_files_imported']++;
    }

    /**
     * Resolve audio duration in milliseconds using FFprobe.
     */
    private function getAudioDurationMs(string $path): int
    {
        $ffprobe = config('ffmpeg.ffprobe_path', 'ffprobe');
        
        $cmd = escapeshellarg($ffprobe) . " -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 -i " . escapeshellarg($path);
        
        $output = shell_exec($cmd);
        if ($output === null) {
            throw new RuntimeException("Failed to execute FFprobe command: {$cmd}");
        }
        
        $durationSeconds = floatval(trim($output));
        if ($durationSeconds <= 0) {
            throw new RuntimeException("Invalid duration resolved via FFprobe: {$durationSeconds} seconds.");
        }
        
        return (int) round($durationSeconds * 1000);
    }
}
