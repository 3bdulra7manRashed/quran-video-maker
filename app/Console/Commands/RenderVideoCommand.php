<?php

namespace App\Console\Commands;

use App\Modules\Layout\Services\FontResolver;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Rendering\Services\RenderPipeline;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RenderVideoCommand extends Command
{
    protected $signature = 'render:video {surah_number : The Surah number (8, 78, or 108)}';

    protected $description = 'Render a vertical reel video for a given Surah using static QCF glyphs and audio.';

    public function handle(RenderPipeline $pipeline, FontResolver $fontResolver): int
    {
        $surahNumber = (int) $this->argument('surah_number');

        $this->info("Initializing rendering pipeline for Surah {$surahNumber}...");

        try {
            $this->validatePrerequisites($surahNumber, $fontResolver);
        } catch (\Throwable $e) {
            $this->error("Validation failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        try {
            $outputPath = $pipeline->render($surahNumber);
            $this->info("Successfully generated video!");
            $this->line("Output Path: {$outputPath}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Rendering failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Validate all prerequisites before initiating the render pipeline.
     */
    private function validatePrerequisites(int $surahNumber, FontResolver $fontResolver): void
    {
        // 1. Validate supported surahs
        $supportedSurahs = [8, 78, 108];
        if (!in_array($surahNumber, $supportedSurahs, true)) {
            throw new InvalidArgumentException(
                "Surah {$surahNumber} is not supported in Layout Engine MVP. Supported surahs are: " . implode(', ', $supportedSurahs)
            );
        }

        // 2. Validate Surah exists in database
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new InvalidArgumentException("Surah {$surahNumber} not found in database. Run 'php artisan import:surahs' first.");
        }

        // 3. Validate Reciter and Audio exists in database
        $reciter = Reciter::where('slug', 'yasser-al-dosari')->first();
        if (!$reciter) {
            throw new InvalidArgumentException("Default reciter 'yasser-al-dosari' not found in database. Run 'php artisan import:audio' first.");
        }

        $audioFile = AudioFile::where('reciter_id', $reciter->id)
            ->where('surah_number', $surahNumber)
            ->first();
        if (!$audioFile) {
            throw new InvalidArgumentException("Audio file metadata not found in database for Surah {$surahNumber}.");
        }

        $absoluteAudioPath = storage_path('app/quran/audio/' . $audioFile->file_path);
        if (!file_exists($absoluteAudioPath)) {
            throw new InvalidArgumentException("Physical audio file does not exist on disk: {$absoluteAudioPath}");
        }

        // 4. Validate Glyph file exists
        $glyphPath = storage_path("app/quran/glyph/surah_{$surahNumber}.json");
        if (!file_exists($glyphPath)) {
            throw new InvalidArgumentException("Glyph source file not found: {$glyphPath}");
        }

        // 5. Validate QCF header mapping exists in configuration
        $headersConfig = config('qcf_surah_headers');
        if (!isset($headersConfig[$surahNumber])) {
            throw new InvalidArgumentException("No QCF_BSML header mapping configured for Surah {$surahNumber} in config/qcf_surah_headers.php.");
        }

        // 6. Validate QCF fonts needed for this Surah exist on disk
        $neededPages = $surah->ayahs()->pluck('page_number')->unique();
        if ($neededPages->isEmpty()) {
            // Fallback to checking surah page bounds if ayahs aren't imported
            $neededPages = range($surah->start_page, $surah->end_page);
        }

        foreach ($neededPages as $page) {
            try {
                $fontResolver->resolve($page);
            } catch (\Throwable $e) {
                throw new InvalidArgumentException("Font validation failed for page {$page}: {$e->getMessage()}");
            }
        }
    }
}
