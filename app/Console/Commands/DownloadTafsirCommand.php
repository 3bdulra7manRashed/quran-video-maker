<?php

namespace App\Console\Commands;

use App\Modules\Quran\Models\AyahTranslation;
use App\Modules\Quran\Models\Surah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class DownloadTafsirCommand extends Command
{
    protected $signature = 'quran:download-tafsir
                            {--resource=16 : Quran.com tafsir resource ID (16 for Al-Tafsir Al-Muyassar)}
                            {--name=ar-tafsir-muyassar : Source name for database and JSON files}';

    protected $description = 'Download verse tafsirs from Quran.com API v4 and format for database import and prompt generation.';

    public function handle(): int
    {
        $resourceId = (int) $this->option('resource');
        $sourceName = $this->option('name');

        $this->info("Fetching Tafsir resource ID {$resourceId} ({$sourceName}) from Quran.com API v4...");

        $surahs = Surah::orderBy('number')->get();

        if ($surahs->isEmpty()) {
            $this->warn("No Surahs found in database. Running import:surahs first...");
            $this->call('import:surahs');
            $surahs = Surah::orderBy('number')->get();
        }

        $tafsirDir = storage_path('app/quran/tafsir');
        File::ensureDirectoryExists($tafsirDir);

        $totalVersesProcessed = 0;
        $upsertBuffer = [];

        $bar = $this->output->createProgressBar($surahs->count());
        $bar->start();

        foreach ($surahs as $surah) {
            $surahNumber = $surah->number;
            $url = "https://api.quran.com/api/v4/tafsirs/{$resourceId}/by_chapter/{$surahNumber}?per_page=300";

            $response = Http::withoutVerifying()
                ->timeout(60)
                ->get($url);

            if ($response->failed()) {
                $this->error("\nFailed to fetch Tafsir for Surah {$surahNumber} ({$url}).");
                return self::FAILURE;
            }

            $data = $response->json();
            $tafsirs = $data['tafsirs'] ?? [];

            foreach ($tafsirs as $item) {
                $verseKey = $item['verse_key'] ?? null; // e.g. "1:1"
                if (!$verseKey || !str_contains($verseKey, ':')) {
                    continue;
                }

                parts:
                [$sNum, $aNum] = explode(':', $verseKey);
                $surahNum = (int) $sNum;
                $ayahNum = (int) $aNum;

                $rawText = $item['text'] ?? '';
                $cleanText = trim(strip_tags($rawText));

                // 1. Buffer for AyahTranslation upsert
                $upsertBuffer[] = [
                    'source'       => $sourceName,
                    'surah_number' => $surahNum,
                    'ayah_number'  => $ayahNum,
                    'text'         => $cleanText,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];

                // 2. Save JSON file for PromptBuilder
                $jsonPath = "{$tafsirDir}/tafsir_{$surahNum}_{$ayahNum}.json";
                $jsonPayload = [
                    'tafsir' => [
                        'resource_id' => $resourceId,
                        'text'        => $cleanText,
                    ],
                ];
                File::put($jsonPath, json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $totalVersesProcessed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Upserting {$totalVersesProcessed} Tafsir records into database...");

        $chunks = array_chunk($upsertBuffer, 500);
        foreach ($chunks as $chunk) {
            AyahTranslation::upsert(
                $chunk,
                ['source', 'surah_number', 'ayah_number'],
                ['text', 'updated_at']
            );
        }

        $this->info("Successfully imported {$totalVersesProcessed} Tafsir entries into database and storage.");
        return self::SUCCESS;
    }
}
