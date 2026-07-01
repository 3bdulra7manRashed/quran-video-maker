<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Quran\Models\AyahTranslation;

class ImportVerseTranslationsCommand extends Command
{
    protected $signature = 'import:verse-translations
                            {--source=sahih_international : The source/filename of translations (without .json)}';

    protected $description = 'Import verse translations from local JSON file into the database.';

    public function handle(): int
    {
        $source = $this->option('source');
        $filePath = storage_path("app/quran/translations/{$source}.json");

        if (!file_exists($filePath)) {
            $this->error("Translation JSON file not found at: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Importing translations from: {$filePath}...");

        $contents = file_get_contents($filePath);
        $records = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON data: " . json_last_error_msg());
            return self::FAILURE;
        }

        $this->info("Found " . count($records) . " records to import.");

        $chunks = array_chunk($records, 500);
        $totalImported = 0;

        foreach ($chunks as $chunk) {
            $upsertData = [];
            foreach ($chunk as $row) {
                $upsertData[] = [
                    'source' => $source,
                    'surah_number' => $row['surah'],
                    'ayah_number' => $row['ayah'],
                    'text' => $row['text'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Perform rapid upsert
            AyahTranslation::upsert(
                $upsertData,
                ['source', 'surah_number', 'ayah_number'],
                ['text', 'updated_at']
            );

            $totalImported += count($chunk);
            $this->info("Imported {$totalImported}/" . count($records) . " records...");
        }

        $this->info("Import completed successfully!");
        return self::SUCCESS;
    }
}
