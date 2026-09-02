<?php

namespace App\Jobs;

use App\Modules\Dataset\Providers\DatasetProvider;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PrepareDatasetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $reciterSlug;
    protected int $surahNumber;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(string $reciterSlug, int $surahNumber)
    {
        $this->reciterSlug = $reciterSlug;
        $this->surahNumber = $surahNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(DatasetProvider $provider): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        Log::info("[PrepareDatasetJob] Starting preparation for {$this->reciterSlug} Surah {$this->surahNumber}");

        // TODO: Future dataset preparation tracking implementation
        // Table: dataset_preparations
        // Columns: id, reciter_id, surah_number, status (Preparing/Ready/Failed), started_at, finished_at, error_message, created_at, updated_at

        $reciter = Reciter::where('slug', $this->reciterSlug)->first();
        if (!$reciter) {
            throw new RuntimeException("Reciter '{$this->reciterSlug}' not found in database.");
        }

        $surah = Surah::where('number', $this->surahNumber)->first();
        if (!$surah) {
            throw new RuntimeException("Surah {$this->surahNumber} not found in database.");
        }

        // 1. Idempotent Glyphs Step
        $destFile = storage_path("app/quran/glyph/surah_{$this->surahNumber}.json");
        $jsonExists = file_exists($destFile) && filesize($destFile) > 0;

        if (!$jsonExists) {
            $provider->downloadGlyphs($this->surahNumber);
            $provider->importGlyphs($this->surahNumber);
            Log::info("[PrepareDatasetJob] Glyphs downloaded and imported.");
        } elseif (!$provider->determineGlyphsAvailability($this->surahNumber)) {
            $provider->importGlyphs($this->surahNumber);
            Log::info("[PrepareDatasetJob] Glyphs file existed on disk; imported missing glyphs into database.");
        } else {
            Log::info("[PrepareDatasetJob] Glyphs already available on disk and in database. Skipping download/import.");
        }

        // Custom reciter source logic bypass
        if ($reciter->source === 'custom') {
            Log::info("[PrepareDatasetJob] Custom reciter '{$this->reciterSlug}' detected. Skipping audio and timing downloads.");
            Log::info("[PrepareDatasetJob] Completed preparation for {$this->reciterSlug} Surah {$this->surahNumber}");
            return;
        }

        // 2. Idempotent Audio Step
        if (!$provider->determineAudioAvailability($this->reciterSlug, $this->surahNumber)) {
            $provider->downloadAudio($this->reciterSlug, $this->surahNumber);
            Log::info("[PrepareDatasetJob] Audio downloaded and metadata registered.");
        } else {
            Log::info("[PrepareDatasetJob] Audio already available on disk. Skipping download/registration.");
        }

        // 3. Idempotent Timings Step
        if (!$provider->determineTimingsAvailability($this->reciterSlug, $this->surahNumber)) {
            try {
                $provider->downloadTimings($this->reciterSlug, $this->surahNumber);
                $provider->importTimings($this->reciterSlug, $this->surahNumber);
                Log::info("[PrepareDatasetJob] Timings downloaded and imported.");
            } catch (\Throwable $e) {
                // Graceful fallback timing mode logic on Quran.com endpoint failure
                Log::warning("[PrepareDatasetJob] Timing download failed: {$e->getMessage()}. Gracefully falling back to proportional mode.");
            }
        } else {
            Log::info("[PrepareDatasetJob] Timings already available on disk. Skipping download/import.");
        }

        Log::info("[PrepareDatasetJob] Completed preparation for {$this->reciterSlug} Surah {$this->surahNumber}");
    }
}
