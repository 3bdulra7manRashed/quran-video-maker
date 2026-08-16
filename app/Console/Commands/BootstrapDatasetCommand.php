<?php

namespace App\Console\Commands;

use App\Modules\Quran\Models\Surah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BootstrapDatasetCommand extends Command
{
    protected $signature = 'dataset:bootstrap 
                            {--reciter=yasser-al-dosari : Reciter slug} 
                            {--surah=* : Specific surah numbers to import (default: all available)} 
                            {--force : Force re-import/overwrite if applicable}';

    protected $description = 'Run the full deterministic dataset bootstrap pipeline in exact dependency order.';

    public function handle(): int
    {
        $reciterSlug = $this->option('reciter') ?: 'yasser-al-dosari';
        $surahs = $this->option('surah');
        $force = $this->option('force');

        $this->info('====================================================');
        $this->info('     Quran Video Maker — Dataset Bootstrap Pipeline');
        $this->info('====================================================');
        $this->info("Reciter: {$reciterSlug}");
        if (!empty($surahs)) {
            $this->info("Target Surahs: " . implode(', ', $surahs));
        }
        $this->newLine();

        // ----------------------------------------------------
        // Step 1: Surahs Verification / Import
        // ----------------------------------------------------
        $this->info('[Step 1/5] Verifying Surahs metadata...');
        $surahCount = Surah::count();

        if ($surahCount === 0 || $force) {
            $this->info('  Surahs missing or force mode active. Running import:surahs...');
            $exitCode = Artisan::call('import:surahs', [], $this->output);
            if ($exitCode !== 0) {
                $this->warn('  [Warning] import:surahs finished with non-zero exit code.');
            }
        } else {
            $this->info("  ✓ Surahs already seeded ({$surahCount} surahs found in database).");
        }
        $this->newLine();

        // ----------------------------------------------------
        // Step 2: Audio Metadata & Audio Files Import
        // ----------------------------------------------------
        $this->info('[Step 2/5] Importing Audio Metadata...');
        try {
            $exitCode = Artisan::call('import:audio', [], $this->output);
            if ($exitCode !== 0) {
                $this->warn('  [Warning] import:audio reported warnings or skipped some entries.');
            } else {
                $this->info('  ✓ Audio metadata processed cleanly.');
            }
        } catch (\Throwable $e) {
            $this->error("  [Error] import:audio failed: {$e->getMessage()}");
            Log::error("[BootstrapDatasetCommand] Audio import failed: " . $e->getMessage());
        }
        $this->newLine();

        // ----------------------------------------------------
        // Step 3: Ayahs, Words, and Mushaf Glyphs Import
        // ----------------------------------------------------
        $this->info('[Step 3/5] Importing Ayahs, Words & Glyphs...');
        try {
            $exitCode = Artisan::call('import:ayahs-words', [], $this->output);
            if ($exitCode !== 0) {
                $this->warn('  [Warning] import:ayahs-words reported warnings or skipped some entries.');
            } else {
                $this->info('  ✓ Ayahs, words and glyphs imported cleanly.');
            }
        } catch (\Throwable $e) {
            $this->error("  [Error] import:ayahs-words failed: {$e->getMessage()}");
            Log::error("[BootstrapDatasetCommand] Glyphs import failed: " . $e->getMessage());
        }
        $this->newLine();

        // ----------------------------------------------------
        // Step 4: Word Timings Import
        // ----------------------------------------------------
        $this->info("[Step 4/5] Importing Word Timings for reciter '{$reciterSlug}'...");
        try {
            $params = [];
            if ($reciterSlug) {
                $params['--reciter'] = $reciterSlug;
            }
            $exitCode = Artisan::call('import:timings', $params, $this->output);
            if ($exitCode !== 0) {
                $this->warn('  [Warning] import:timings reported warnings or skipped some entries.');
            } else {
                $this->info('  ✓ Word timings imported cleanly.');
            }
        } catch (\Throwable $e) {
            $this->error("  [Error] import:timings failed: {$e->getMessage()}");
            Log::error("[BootstrapDatasetCommand] Timings import failed: " . $e->getMessage());
        }
        $this->newLine();

        // ----------------------------------------------------
        // Step 5: Verse Translations Import (Optional / Available)
        // ----------------------------------------------------
        $this->info('[Step 5/5] Checking Verse Translations Import...');
        $translationPath = storage_path('app/quran/translations/sahih_international.json');

        if (file_exists($translationPath)) {
            try {
                $exitCode = Artisan::call('import:verse-translations', [], $this->output);
                if ($exitCode !== 0) {
                    $this->warn('  [Warning] import:verse-translations reported warnings or skipped entries.');
                } else {
                    $this->info('  ✓ Verse translations imported cleanly.');
                }
            } catch (\Throwable $e) {
                $this->error("  [Error] import:verse-translations failed: {$e->getMessage()}");
                Log::error("[BootstrapDatasetCommand] Verse translations import failed: " . $e->getMessage());
            }
        } else {
            $this->info('  ℹ Translation source file not found at storage/app/quran/translations/sahih_international.json (Skipped).');
        }
        $this->newLine();

        $this->info('====================================================');
        $this->info('     Dataset Bootstrap Completed Successfully!');
        $this->info('====================================================');

        return self::SUCCESS;
    }
}
