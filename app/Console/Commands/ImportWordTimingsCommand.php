<?php

namespace App\Console\Commands;

use App\Modules\Import\Services\ImportWordTimingsService;
use Illuminate\Console\Command;

class ImportWordTimingsCommand extends Command
{
    protected $signature = 'import:timings
                            {--source= : Specific JSON file path or directory containing timings_*_*.json (default: storage/app/quran/timings/)}';

    protected $description = 'Import word timings from local JSON files into the database.';

    public function handle(ImportWordTimingsService $service): int
    {
        $source = $this->option('source');

        $this->info($source 
            ? "Importing word timings from specific source: {$source}" 
            : 'Importing word timings from default timings directory...'
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
                ['Files Processed', $report['files_processed']],
                ['Ayahs Processed', $report['ayahs_processed']],
                ['Timings Imported', $report['timings_imported']],
                ['Skipped Ayahs (exist)', $report['skipped']],
                ['Errors Count', $report['errors']],
                ['Duration', $report['duration_ms'] . ' ms'],
                ['Imported At', $report['imported_at']],
            ]
        );

        $this->newLine();

        if ($report['timings_imported'] > 0) {
            $this->info("Successfully imported {$report['timings_imported']} word timing entries.");
        } elseif ($report['skipped'] > 0 && $report['errors'] === 0) {
            $this->info('All word timings already match the files. Nothing to import.');
        }

        if ($report['errors'] > 0) {
            $this->error("Encountered {$report['errors']} errors during the import process. Check the logs for details.");
        }
    }
}
