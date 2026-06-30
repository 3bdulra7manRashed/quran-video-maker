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
                $this->renderJob->to_ayah
            );

            $this->renderJob->update([
                'status' => 'completed',
                'output_path' => $outputPath,
                'output_filename' => basename($outputPath),
                'progress' => 100,
                'finished_at' => now(),
                'completed_at' => now(),
            ]);

            Log::info("[GenerateVideoJob] Successfully completed video render job", [
                'uuid' => $this->renderJob->uuid,
                'output' => $outputPath,
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
