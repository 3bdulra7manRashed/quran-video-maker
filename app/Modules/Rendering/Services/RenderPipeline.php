<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Layout\Services\PaginationService;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Quran\Models\Word;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RenderPipeline
{
    protected FrameRenderer $frameRenderer;
    protected VideoComposer $videoComposer;
    protected PaginationService $paginationService;
    protected FrameTimingService $frameTimingService;

    public function __construct(
        FrameRenderer $frameRenderer,
        VideoComposer $videoComposer,
        PaginationService $paginationService,
        FrameTimingService $frameTimingService
    ) {
        $this->frameRenderer = $frameRenderer;
        $this->videoComposer = $videoComposer;
        $this->paginationService = $paginationService;
        $this->frameTimingService = $frameTimingService;
    }

    /**
     * Run the video rendering pipeline for a given Surah number.
     *
     * @param int $surahNumber
     * @return string Path to the generated video.
     */
    public function render(int $surahNumber): string
    {
        Log::info("[RenderPipeline] Starting pagination rendering pipeline for Surah {$surahNumber}");

        // 1. Select Surah
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new RuntimeException("Surah {$surahNumber} not found in database.");
        }

        // 2. Select default Reciter
        $reciter = Reciter::where('slug', 'yasser-al-dosari')->first();
        if (!$reciter) {
            throw new RuntimeException("Default reciter 'yasser-al-dosari' not found in database.");
        }

        // 3. Load AudioFile metadata
        $audioFile = AudioFile::where('reciter_id', $reciter->id)
            ->where('surah_number', $surahNumber)
            ->first();

        if (!$audioFile) {
            throw new RuntimeException("Audio file metadata not found for Surah {$surahNumber} and reciter '{$reciter->slug}'.");
        }

        $absoluteAudioPath = storage_path('app/quran/audio/' . $audioFile->file_path);
        if (!file_exists($absoluteAudioPath)) {
            throw new RuntimeException("Audio file does not exist on disk: {$absoluteAudioPath}");
        }

        $totalDuration = $audioFile->duration_ms / 1000.0;
        if ($totalDuration <= 0) {
            throw new RuntimeException("Invalid audio duration in database: {$audioFile->duration_ms} ms");
        }

        // 4. Load and group Quran words by [page_number, line_number]
        $ayahIds = $surah->ayahs()->pluck('id');
        $words = Word::whereIn('ayah_id', $ayahIds)
            ->orderBy('page_number')
            ->orderBy('line_number')
            ->orderBy('ayah_id')
            ->orderBy('word_index')
            ->get();

        $lines = [];
        foreach ($words as $word) {
            $page = $word->page_number;
            $line = $word->line_number ?? 0;
            $key = "{$page}_{$line}";

            if (!isset($lines[$key])) {
                $lines[$key] = [
                    'page' => $page,
                    'line' => $line,
                    'words' => [],
                ];
            }
            $lines[$key]['words'][] = $word;
        }

        $sequentialLines = array_values($lines);

        // 5. Paginate lines into frames
        $frames = $this->paginationService->paginate($sequentialLines);
        if (empty($frames)) {
            throw new RuntimeException("Pagination service returned no frames for Surah {$surahNumber}.");
        }

        Log::info("[RenderPipeline] Paginated Surah {$surahNumber} into " . count($frames) . " frames.");

        // 6. Resolve frame timings
        $frames = $this->frameTimingService->populateTimings($frames, $totalDuration);

        // 7. Generate debug logs (pagination & timings)
        $this->writeDebugJson($surahNumber, $frames);
        $this->writeDebugTimingsJson($surahNumber, $frames);

        // 8. Render all frames as PNGs and build frame schedule
        $composerFrames = [];
        foreach ($frames as $frame) {
            $paddedIndex = sprintf('%03d', $frame->index);
            $framePath = storage_path("app/rendered_frames/surah_{$surahNumber}/frame_{$paddedIndex}.png");

            $this->frameRenderer->renderFrame($surah, $frame, $framePath);

            $frameDurationSec = ($frame->endMs - $frame->startMs) / 1000.0;

            $composerFrames[] = [
                'image_path' => $framePath,
                'duration' => $frameDurationSec,
            ];
        }

        // 9. Compose the video from frames and audio
        $outputVideoPath = storage_path("app/output/surah_{$surahNumber}.mp4");
        $this->videoComposer->compose($composerFrames, $absoluteAudioPath, $outputVideoPath, $totalDuration);

        Log::info("[RenderPipeline] Completed pagination pipeline for Surah {$surahNumber}");

        return $outputVideoPath;
    }

    /**
     * Write debugging pagination JSON file.
     *
     * @param int $surahNumber
     * @param array $frames
     * @return void
     */
    protected function writeDebugJson(int $surahNumber, array $frames): void
    {
        $debugDir = storage_path('app/debug/pagination');
        if (!file_exists($debugDir)) {
            mkdir($debugDir, 0755, true);
        }

        $debugData = [];
        foreach ($frames as $frame) {
            $debugData[] = [
                'frame' => $frame->index,
                'startLine' => $frame->startLine,
                'endLine' => $frame->endLine,
                'lineCount' => count($frame->lines),
            ];
        }

        $debugFile = $debugDir . DIRECTORY_SEPARATOR . "surah_{$surahNumber}_frames.json";
        file_put_contents($debugFile, json_encode($debugData, JSON_PRETTY_PRINT));
        Log::info("[RenderPipeline] Written pagination debug log to {$debugFile}");
    }

    /**
     * Write debugging timings JSON file.
     *
     * @param int $surahNumber
     * @param array $frames
     * @return void
     */
    protected function writeDebugTimingsJson(int $surahNumber, array $frames): void
    {
        $debugDir = storage_path('app/debug/timings');
        if (!file_exists($debugDir)) {
            mkdir($debugDir, 0755, true);
        }

        $debugData = [];
        foreach ($frames as $frame) {
            $debugData[] = [
                'frame' => $frame->index,
                'startLine' => $frame->startLine,
                'endLine' => $frame->endLine,
                'startMs' => $frame->startMs,
                'endMs' => $frame->endMs,
                'durationMs' => $frame->endMs - $frame->startMs,
            ];
        }

        $debugFile = $debugDir . DIRECTORY_SEPARATOR . "surah_{$surahNumber}_frame_timings.json";
        file_put_contents($debugFile, json_encode($debugData, JSON_PRETTY_PRINT));
        Log::info("[RenderPipeline] Written timings debug log to {$debugFile}");
    }
}
