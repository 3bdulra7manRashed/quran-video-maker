<?php

namespace App\Console\Commands;

use App\Modules\Import\Services\ImportSurahsService;
use Illuminate\Console\Command;

class ImportSurahsCommand extends Command
{
    protected $signature = 'import:surahs
                            {--source= : Path to the chapters JSON file (default: storage/app/quran/chapters.json)}';

    protected $description = 'Import Surah metadata from a local chapters JSON file into the database.';

    public function handle(ImportSurahsService $service): int
    {
        $source = $this->option('source')
            ?? storage_path('app/quran/chapters.json');

        $this->info("Importing Surahs from: {$source}");
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
                ['Total in source', $report['total']],
                ['Imported', $report['imported']],
                ['Skipped (already exist)', $report['skipped']],
                ['Errors', $report['errors']],
                ['Duration', $report['duration_ms'] . ' ms'],
                ['Imported At', $report['imported_at']],
            ]
        );

        $this->newLine();

        if ($report['imported'] > 0) {
            $this->info("Successfully imported {$report['imported']} surahs.");
        } elseif ($report['skipped'] === $report['total']) {
            $this->info('All surahs already exist. Nothing to import.');
        }
    }
}
