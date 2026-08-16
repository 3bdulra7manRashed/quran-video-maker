<?php

namespace App\Console\Commands;

use App\Modules\Quran\Models\Surah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DownloadTranslationsCommand extends Command
{
    protected $signature = 'quran:download-translations 
                            {--resource=20 : Quran.com translation resource ID (20 for Sahih International)}
                            {--name=sahih_international : Output JSON filename without extension}';

    protected $description = 'Download verse translations from Quran.com API v4 and format for import.';

    public function handle(): int
    {
        $resourceId = (int) $this->option('resource');
        $fileName = $this->option('name');

        $this->info("Fetching translation resource ID {$resourceId} from Quran.com API v4...");

        $targetDir = storage_path('app/quran/translations');
        File::ensureDirectoryExists($targetDir);
        $targetPath = "{$targetDir}/{$fileName}.json";

        // Query Quran.com API v4 translations endpoint
        $url = "https://api.quran.com/api/v4/quran/translations/{$resourceId}";
        
        $response = Http::withoutVerifying()
            ->timeout(60)
            ->get($url);

        if ($response->failed()) {
            $this->error("Failed to fetch translation data from Quran.com API ({$url}).");
            return self::FAILURE;
        }

        $data = $response->json();
        $translations = $data['translations'] ?? [];

        if (empty($translations)) {
            $this->error("No translations returned in response from Quran.com API.");
            return self::FAILURE;
        }

        $totalApiItems = count($translations);
        $this->info("Successfully fetched {$totalApiItems} translation entries.");

        // Resolve Surahs to align verse numbers correctly
        $surahs = Surah::orderBy('number')->get();

        if ($surahs->isEmpty()) {
            $this->warn("No Surahs found in database. Running SurahSeeder / import:surahs first...");
            $this->call('import:surahs');
            $surahs = Surah::orderBy('number')->get();
        }

        $totalDbVerses = $surahs->sum('verses_count');

        if ($totalApiItems !== $totalDbVerses) {
            $this->warn("Warning: API entries count ({$totalApiItems}) differs from DB verses count ({$totalDbVerses}). Mapping by available entries...");
        }

        // Format records as expected by ImportVerseTranslationsCommand
        $records = [];
        $index = 0;

        foreach ($surahs as $surah) {
            for ($v = 1; $v <= $surah->verses_count; $v++) {
                if ($index >= $totalApiItems) {
                    break 2;
                }

                $item = $translations[$index++];
                $records[] = [
                    'surah' => $surah->number,
                    'ayah'  => $v,
                    'text'  => $item['text'] ?? '',
                ];
            }
        }

        File::put($targetPath, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Saved " . count($records) . " formatted translation records to: {$targetPath}");
        return self::SUCCESS;
    }
}
