<?php

namespace App\Console\Commands;

use App\Modules\Layout\Services\FontResolver;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Rendering\Services\RenderPipeline;
use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RenderVideoCommand extends Command
{
    protected $signature = 'render:video 
                            {surah_number : The Surah number (8, 56, 78, or 108)} 
                            {--reciter=yasser-al-dosari : The reciter slug (yasser-al-dosari or ali-jaber)}
                            {--from= : Start ayah number (optional, inclusive)}
                            {--to= : End ayah number (optional, inclusive)}';

    protected $description = 'Render a vertical reel video for a given Surah using static QCF glyphs and audio.';

    public function handle(RenderPipeline $pipeline, FontResolver $fontResolver, QuranPathResolver $pathResolver): int
    {
        $surahNumber = (int) $this->argument('surah_number');
        $reciterSlug = $this->option('reciter');
        
        $fromAyah = $this->option('from') !== null ? (int) $this->option('from') : null;
        $toAyah = $this->option('to') !== null ? (int) $this->option('to') : null;

        $this->info("Initializing rendering pipeline for Surah {$surahNumber} and reciter '{$reciterSlug}'...");

        try {
            $this->validatePrerequisites($surahNumber, $reciterSlug, $fromAyah, $toAyah, $fontResolver, $pathResolver);
        } catch (\Throwable $e) {
            $this->error("Validation failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        try {
            $outputPath = $pipeline->render($surahNumber, $reciterSlug, $fromAyah, $toAyah);
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
    private function validatePrerequisites(
        int $surahNumber,
        string $reciterSlug,
        ?int $fromAyah,
        ?int $toAyah,
        FontResolver $fontResolver,
        QuranPathResolver $pathResolver
    ): void {
        // 1. Validate supported surahs
        $supportedSurahs = [8, 56, 78, 108];
        if (!in_array($surahNumber, $supportedSurahs, true)) {
            throw new InvalidArgumentException(
                "Surah {$surahNumber} is not supported. Supported surahs are: " . implode(', ', $supportedSurahs)
            );
        }

        // 2. Validate Surah exists in database
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new InvalidArgumentException("Surah {$surahNumber} not found in database. Run 'php artisan import:surahs' first.");
        }

        // 3. Validate Ayah Range (Fail fast checks)
        if ($fromAyah !== null || $toAyah !== null) {
            if ($fromAyah === null || $toAyah === null) {
                throw new InvalidArgumentException("Both --from and --to flags must be specified together for partial rendering.");
            }

            if ($fromAyah < 1) {
                throw new InvalidArgumentException("The --from ayah number must be at least 1. Got: {$fromAyah}.");
            }

            if ($toAyah > $surah->verses_count) {
                throw new InvalidArgumentException("The --to ayah number ({$toAyah}) exceeds total ayahs ({$surah->verses_count}) in Surah {$surahNumber}.");
            }

            if ($fromAyah > $toAyah) {
                throw new InvalidArgumentException("The --from ayah number ({$fromAyah}) must be less than or equal to --to ayah number ({$toAyah}).");
            }
        }

        // 4. Validate Reciter exists in database
        $reciter = Reciter::where('slug', $reciterSlug)->first();
        if (!$reciter) {
            throw new InvalidArgumentException("Reciter '{$reciterSlug}' not found in database. Check that the audio files have been imported.");
        }

        // 5. Validate Audio File exists in database and on disk
        $audioFile = AudioFile::where('reciter_id', $reciter->id)
            ->where('surah_number', $surahNumber)
            ->first();
        if (!$audioFile) {
            throw new InvalidArgumentException("Audio file metadata not found in database for Surah {$surahNumber} and reciter '{$reciterSlug}'.");
        }

        $absoluteAudioPath = $pathResolver->audio($audioFile->file_path);
        if (!file_exists($absoluteAudioPath)) {
            throw new InvalidArgumentException("Physical audio file does not exist on disk: {$absoluteAudioPath}");
        }

        // 6. Validate Glyph file exists
        $glyphPath = storage_path("app/quran/glyph/surah_{$surahNumber}.json");
        if (!file_exists($glyphPath)) {
            throw new InvalidArgumentException("Glyph source file not found: {$glyphPath}");
        }

        // 7. Validate QCF header mapping exists in configuration
        $headersConfig = config('qcf_surah_headers');
        if (!isset($headersConfig[$surahNumber])) {
            throw new InvalidArgumentException("No QCF_BSML header mapping configured for Surah {$surahNumber} in config/qcf_surah_headers.php.");
        }

        // 8. Validate QCF fonts needed for this Surah range exist on disk
        $ayahQuery = $surah->ayahs();
        if ($fromAyah !== null) {
            $ayahQuery = $ayahQuery->where('ayah_number', '>=', $fromAyah);
        }
        if ($toAyah !== null) {
            $ayahQuery = $ayahQuery->where('ayah_number', '<=', $toAyah);
        }
        
        $neededPages = $ayahQuery->pluck('page_number')->unique();
        if ($neededPages->isEmpty()) {
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
