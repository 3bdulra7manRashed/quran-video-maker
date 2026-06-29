<?php

namespace App\Console\Commands;

use App\Modules\Import\Services\ImportAyahsAndWordsService;
use Illuminate\Console\Command;

class ImportAyahsAndWordsCommand extends Command
{
    protected $signature = 'import:ayahs-words
                            {--source= : Specific JSON file path or directory containing surah_*.json (default: storage/app/quran/glyph/)}';

    protected $description = 'Import Ayahs and Words from local JSON files into the database.';

    public function handle(ImportAyahsAndWordsService $service): int
    {
        $source = $this->option('source');

        $this->info($source 
            ? "Importing Ayahs and Words from specific source: {$source}" 
            : 'Importing Ayahs and Words from default glyph directory...'
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
                ['Surahs Processed', $report['surahs_processed']],
                ['Ayahs Imported', $report['ayahs_imported']],
                ['Words Imported', $report['words_imported']],
                ['Skipped Ayahs (exist)', $report['skipped']],
                ['Errors Count', $report['errors']],
                ['Duration', $report['duration_ms'] . ' ms'],
                ['Imported At', $report['imported_at']],
            ]
        );

        $this->newLine();

        if ($report['ayahs_imported'] > 0) {
            $this->info("Successfully imported {$report['ayahs_imported']} ayahs and {$report['words_imported']} words.");
        } elseif ($report['skipped'] > 0 && $report['errors'] === 0) {
            $this->info('All ayahs and words already exist. Nothing to import.');
        }

        if ($report['errors'] > 0) {
            $this->error("Encountered {$report['errors']} errors during the import process. Check the logs for details.");
        }
    }
}
