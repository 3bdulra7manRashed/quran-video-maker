<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Layout\Services\FontResolver;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Quran\Models\Word;
use App\Modules\Segmentation\Services\SegmentationService;
use App\Modules\Segmentation\DTO\Segment;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RenderPipeline
{
    protected SegmentRenderer $segmentRenderer;
    protected VideoComposer $videoComposer;
    protected SegmentationService $segmentationService;
    protected FontResolver $fontResolver;

    public function __construct(
        SegmentRenderer $segmentRenderer,
        VideoComposer $videoComposer,
        SegmentationService $segmentationService,
        FontResolver $fontResolver
    ) {
        $this->segmentRenderer = $segmentRenderer;
        $this->videoComposer = $videoComposer;
        $this->segmentationService = $segmentationService;
        $this->fontResolver = $fontResolver;
    }

    /**
     * Run the video rendering pipeline for a given Surah number.
     *
     * @param int $surahNumber
     * @return string Path to the generated video.
     */
    public function render(int $surahNumber): string
    {
        Log::info("[RenderPipeline] Starting segmentation rendering pipeline for Surah {$surahNumber}");

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

        // 4. Load ordered Word stream
        $ayahIds = $surah->ayahs()->pluck('id');
        $words = Word::whereIn('ayah_id', $ayahIds)
            ->orderBy('page_number')
            ->orderBy('line_number')
            ->orderBy('ayah_id')
            ->orderBy('word_index')
            ->get()
            ->all();

        // 5. Segment the words
        $segments = $this->segmentationService->segment($words, $totalDuration);
        if (empty($segments)) {
            throw new RuntimeException("Segmentation service returned no segments for Surah {$surahNumber}.");
        }

        // Populate segment metrics for debug/report output
        $fontSize = config('layouts.reels.font_size', 56);
        foreach ($segments as $segment) {
            $segment->fontSize = $fontSize;
            $segment->wordCount = count($segment->wordIds);

            if (!empty($segment->words)) {
                $firstWord = $segment->words[0];
                $page = $firstWord->page_number;
                $fontPath = $this->fontResolver->resolve($page);

                $lineText = \App\Modules\Shared\Utils\GlyphStringCompiler::compile($segment->words);

                $bbox = imagettfbbox($fontSize, 0, $fontPath, $lineText);
                $segment->renderedWidth = abs($bbox[4] - $bbox[0]);
            } else {
                $segment->renderedWidth = 0;
            }
        }

        Log::info("[RenderPipeline] Segmented Surah {$surahNumber} into " . count($segments) . " segments.");

        // 6. Generate debug segment JSON file
        $this->writeDebugSegmentsJson($surahNumber, $segments);

        // 7. Render all segments as PNGs and build frame schedule
        $composerFrames = [];
        foreach ($segments as $segment) {
            $paddedIndex = sprintf('%03d', $segment->index);
            $segmentPath = storage_path("app/rendered_segments/surah_{$surahNumber}/segment_{$paddedIndex}.png");

            $this->segmentRenderer->renderSegment($surah, $segment, $segmentPath);

            $segmentDurationSec = ($segment->endMs - $segment->startMs) / 1000.0;

            $composerFrames[] = [
                'image_path' => $segmentPath,
                'duration' => $segmentDurationSec,
            ];
        }

        // 8. Compose the video from segments and audio
        $outputVideoPath = storage_path("app/output/surah_{$surahNumber}.mp4");
        $this->videoComposer->compose($composerFrames, $absoluteAudioPath, $outputVideoPath, $totalDuration);

        Log::info("[RenderPipeline] Completed pagination pipeline for Surah {$surahNumber}");

        return $outputVideoPath;
    }

    /**
     * Write debugging segments JSON file.
     *
     * @param int $surahNumber
     * @param Segment[] $segments
     * @return void
     */
    protected function writeDebugSegmentsJson(int $surahNumber, array $segments): void
    {
        $debugDir = storage_path('app/debug/segments');
        if (!file_exists($debugDir)) {
            mkdir($debugDir, 0755, true);
        }

        $debugData = [];
        foreach ($segments as $segment) {
            $debugData[] = [
                'segment' => $segment->index,
                'startMs' => $segment->startMs,
                'endMs' => $segment->endMs,
                'durationMs' => $segment->durationMs,
                'wordCount' => $segment->wordCount,
                'renderedWidth' => $segment->renderedWidth,
                'fontSize' => $segment->fontSize,
            ];
        }

        $debugFile = $debugDir . DIRECTORY_SEPARATOR . "surah_{$surahNumber}_segments.json";
        file_put_contents($debugFile, json_encode($debugData, JSON_PRETTY_PRINT));
        Log::info("[RenderPipeline] Written segments debug log to {$debugFile}");
    }
}
