<?php

namespace App\Jobs;

use App\Modules\Quran\Models\RenderJob;
use App\Modules\Rendering\Services\RenderPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected RenderJob $renderJob;

    /**
     * Create a new job instance.
     */
    public function __construct(RenderJob $renderJob)
    {
        $this->renderJob = $renderJob;
    }

    /**
     * Execute the job.
     */
    public function handle(RenderPipeline $pipeline): void
    {
        Log::info("[GenerateVideoJob] Starting video render job", [
            'uuid' => $this->renderJob->uuid,
            'surah' => $this->renderJob->surah_number,
        ]);

        // Check if cancelled while pending
        $this->renderJob->refresh();
        if ($this->renderJob->isCancelled() || $this->renderJob->isCancelling()) {
            Log::info("[GenerateVideoJob] Job {$this->renderJob->uuid} was cancelled before starting. Exiting immediately.");
            $this->renderJob->update([
                'status' => 'cancelled',
                'progress' => 0,
                'finished_at' => now(),
                'completed_at' => now(),
            ]);
            return;
        }

        $this->renderJob->update([
            'status' => 'processing',
            'started_at' => now(),
            'progress' => 10, // Initial progress
        ]);

        try {
            $reciter = $this->renderJob->reciter;
            if (!$reciter) {
                throw new \RuntimeException("Reciter associated with this render job not found.");
            }

            // Execute rendering pipeline
            $outputPath = $pipeline->render(
                $this->renderJob->surah_number,
                $reciter->slug,
                $this->renderJob->from_ayah,
                $this->renderJob->to_ayah,
                $this->renderJob->layout ?? 'reels',
                $this->renderJob->max_lines ?? 1,
                (bool) ($this->renderJob->with_translation ?? false),
                $this->renderJob->translation_source ?? 'sahih_international',
                $this->renderJob,
                (bool) ($this->renderJob->use_generated_content ?? false)
            );

            // Probe duration via ffprobe
            $durationSeconds = 0.0;
            try {
                $ffprobe = config('ffmpeg.ffprobe_path', 'ffprobe');
                $cmd = sprintf(
                    '"%s" -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "%s"',
                    $ffprobe,
                    $outputPath
                );
                $output = [];
                $resultCode = -1;
                exec($cmd . ' 2>&1', $output, $resultCode);
                if ($resultCode === 0 && count($output) > 0) {
                    $durationSeconds = (float) trim($output[0]);
                }
            } catch (\Throwable $e) {
                Log::warning("[GenerateVideoJob] ffprobe failed to resolve duration for {$outputPath}: " . $e->getMessage());
            }

            $this->renderJob->update([
                'status' => 'completed',
                'output_path' => $outputPath,
                'output_filename' => basename($outputPath),
                'progress' => 100,
                'duration' => $durationSeconds,
                'finished_at' => now(),
                'completed_at' => now(),
            ]);

            Log::info("[GenerateVideoJob] Successfully completed video render job", [
                'uuid' => $this->renderJob->uuid,
                'output' => $outputPath,
            ]);

        } catch (\App\Modules\Rendering\Exceptions\RenderCancelledException $e) {
            Log::info("[GenerateVideoJob] Cooperative cancellation caught for job {$this->renderJob->uuid}. Starting cleanup.");
            
            try {
                $reciterSlug = $this->renderJob->reciter ? $this->renderJob->reciter->slug : 'yasser-al-dosari';
                $pipeline->cleanup(
                    $this->renderJob->surah_number,
                    $reciterSlug,
                    $this->renderJob->from_ayah,
                    $this->renderJob->to_ayah,
                    $this->renderJob->layout ?? 'reels'
                );
            } catch (\Throwable $cleanupEx) {
                Log::error("[GenerateVideoJob] Cleanup failed after cancellation: " . $cleanupEx->getMessage());
            }

            $this->renderJob->update([
                'status' => 'cancelled',
                'progress' => 0,
                'finished_at' => now(),
                'completed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error("[GenerateVideoJob] Video render job failed", [
                'uuid' => $this->renderJob->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->renderJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
                'completed_at' => now(),
            ]);
        }
    }
}
