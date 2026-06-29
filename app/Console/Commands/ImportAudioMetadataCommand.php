<?php

namespace App\Console\Commands;

use App\Modules\Import\Services\ImportAudioMetadataService;
use Illuminate\Console\Command;

class ImportAudioMetadataCommand extends Command
{
    protected $signature = 'import:audio
                            {--source= : Specific metadata JSON file path or directory containing metadata.json (default: storage/app/quran/audio/)}';

    protected $description = 'Import audio metadata for the reciter and their surah MP3 files into the database.';

    public function handle(ImportAudioMetadataService $service): int
    {
        $source = $this->option('source');

        $this->info($source 
            ? "Importing audio metadata from specific source: {$source}" 
            : 'Importing audio metadata from default audio directory...'
        );
        $this->newLine();

        try {
            $report = $service->import($source);
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->displayReport($report);

        if ($report['errors'] > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function displayReport(array $report): void
    {
        $this->info('=== Import Report ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Source', $report['source']],
                ['Reciters Imported', $report['reciters_imported']],
                ['Audio Files Imported', $report['audio_files_imported']],
                ['Skipped Audio Files', $report['skipped']],
                ['Errors Count', $report['errors']],
                ['Duration', $report['duration_ms'] . ' ms'],
                ['Imported At', $report['imported_at']],
            ]
        );

        $this->newLine();

        if ($report['audio_files_imported'] > 0) {
            $this->info("Successfully imported {$report['audio_files_imported']} audio files.");
        } elseif ($report['skipped'] > 0 && $report['errors'] === 0) {
            $this->info('All audio files metadata already matches the files. Nothing to import.');
        }

        if ($report['errors'] > 0) {
            $this->error("Encountered {$report['errors']} errors during the import process. Check the logs for details.");
        }
    }
}
