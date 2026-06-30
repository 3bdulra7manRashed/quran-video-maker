<?php

namespace App\Console\Commands;

use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Ayah;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Import\Services\ImportManualTimingsService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

class ImportManualTimingsCommand extends Command
{
    protected $signature = 'import:manual-timings 
                            {path : Path to the manual timings JSON file} 
                            {--reciter= : The reciter slug (e.g. ali-jaber)}';

    protected $description = 'Import manual word timings for a specific ayah range of a Surah';

    public function handle(ImportManualTimingsService $service): int
    {
        $path = $this->argument('path');
        $reciterSlug = $this->option('reciter');

        if (!$reciterSlug) {
            $this->error("The --reciter option is required.");
            return self::FAILURE;
        }

        // Validate reciter
        $reciter = Reciter::where('slug', $reciterSlug)->first();
        if (!$reciter) {
            $this->error("Reciter with slug '{$reciterSlug}' not found in database.");
            return self::FAILURE;
        }

        // Load JSON
        if (!file_exists($path)) {
            $this->error("Timings file not found: {$path}");
            return self::FAILURE;
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON format: " . json_last_error_msg());
            return self::FAILURE;
        }

        $surahNumber = $data['surah'] ?? null;
        $fromAyah = $data['from_ayah'] ?? null;
        $toAyah = $data['to_ayah'] ?? null;
        $jsonWords = $data['words'] ?? [];

        if (!$surahNumber || !$fromAyah || !$toAyah || empty($jsonWords)) {
            $this->error("JSON must contain 'surah', 'from_ayah', 'to_ayah', and a non-empty 'words' array.");
            return self::FAILURE;
        }

        // Locate Surah
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            $this->error("Surah {$surahNumber} not found in database.");
            return self::FAILURE;
        }

        // Locate Ayahs
        $ayahs = Ayah::where('surah_id', $surah->id)
            ->whereBetween('ayah_number', [$fromAyah, $toAyah])
            ->orderBy('ayah_number')
            ->get();

        if ($ayahs->isEmpty()) {
            $this->error("No ayahs found in database for range {$fromAyah}-{$toAyah}.");
            return self::FAILURE;
        }

        // Query spoken words
        $spokenWords = Word::whereIn('ayah_id', $ayahs->pluck('id'))
            ->where('char_type', 'word')
            ->orderBy('ayah_id')
            ->orderBy('word_index')
            ->get();

        $dbCount = $spokenWords->count();
        $jsonCount = count($jsonWords);

        $this->info("=== Importer Diagnostics ===");
        $this->line("Surah: {$surahNumber} ({$surah->name_simple})");
        $this->line("Ayah range: {$fromAyah} to {$toAyah}");
        $this->line("Database spoken words count: {$dbCount}");
        $this->line("JSON manual words count: {$jsonCount}");

        if ($dbCount !== $jsonCount) {
            $this->warn("Diagnostic warning: Word count mismatch! DB spoken words count ({$dbCount}) does not match JSON count ({$jsonCount}).");
            $this->error("Failing safely. Timings were not imported.");
            return self::FAILURE;
        }

        // Validate and match words (for diagnostic warning output)
        $mismatches = [];
        for ($i = 0; $i < $dbCount; $i++) {
            $dbWord = $spokenWords[$i];
            $jsonWord = $jsonWords[$i];

            $normDb = $service->normalizeText($dbWord->uthmani_text ?? '');
            $normJson = $service->normalizeText($jsonWord['text'] ?? '');

            if ($normDb !== $normJson) {
                $mismatches[] = [
                    'index' => $i,
                    'db_word' => $dbWord->uthmani_text,
                    'json_word' => $jsonWord['text'],
                    'db_normalized' => $normDb,
                    'json_normalized' => $normJson
                ];
            }
        }

        if (!empty($mismatches)) {
            $this->warn("Diagnostic warning: Word text mismatch detected! Normalization details:");
            foreach ($mismatches as $m) {
                $this->line("  Index {$m['index']} -> DB: '{$m['db_word']}' (Normalized: '{$m['db_normalized']}') | JSON: '{$m['json_word']}' (Normalized: '{$m['json_normalized']}')");
            }
            $this->warn("Proceeding with sequential alignment because word counts match.");
        }

        try {
            $service->import($data, $reciter->id);
            $this->info("Successfully imported manual timings for Surah {$surahNumber} (Ayahs {$fromAyah}-{$toAyah}).");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Database update failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
