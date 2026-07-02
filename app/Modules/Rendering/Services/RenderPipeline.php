<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Layout\Services\FontResolver;
use App\Modules\Layout\Strategies\ReelSingleLineLayoutStrategy;
use App\Modules\Layout\Strategies\YouTubeMushafLayoutStrategy;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Quran\Models\Word;
use App\Modules\Segmentation\Services\SegmentationService;
use App\Modules\Segmentation\DTO\Segment;
use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RenderPipeline
{
    protected SegmentRenderer $segmentRenderer;
    protected VideoComposer $videoComposer;
    protected SegmentationService $segmentationService;
    protected FontResolver $fontResolver;
    protected QuranPathResolver $pathResolver;
    protected ReelSingleLineLayoutStrategy $layoutStrategy;
    protected YouTubeMushafLayoutStrategy $youtubeLayoutStrategy;
    protected WordTimingResolver $timingResolver;
    protected \App\Modules\Dataset\Services\DatasetCoverageResolver $coverageResolver;
    protected \App\Modules\Rendering\Services\TranslationSegmentBuilder $translationSegmentBuilder;
    protected \App\Modules\Rendering\Services\RenderCancellationGuard $cancellationGuard;

    public function __construct(
        SegmentRenderer $segmentRenderer,
        VideoComposer $videoComposer,
        SegmentationService $segmentationService,
        FontResolver $fontResolver,
        QuranPathResolver $pathResolver,
        ReelSingleLineLayoutStrategy $layoutStrategy,
        YouTubeMushafLayoutStrategy $youtubeLayoutStrategy,
        WordTimingResolver $timingResolver,
        \App\Modules\Dataset\Services\DatasetCoverageResolver $coverageResolver,
        \App\Modules\Rendering\Services\TranslationSegmentBuilder $translationSegmentBuilder,
        \App\Modules\Rendering\Services\RenderCancellationGuard $cancellationGuard
    ) {
        $this->segmentRenderer = $segmentRenderer;
        $this->videoComposer = $videoComposer;
        $this->segmentationService = $segmentationService;
        $this->fontResolver = $fontResolver;
        $this->pathResolver = $pathResolver;
        $this->layoutStrategy = $layoutStrategy;
        $this->youtubeLayoutStrategy = $youtubeLayoutStrategy;
        $this->timingResolver = $timingResolver;
        $this->coverageResolver = $coverageResolver;
        $this->translationSegmentBuilder = $translationSegmentBuilder;
        $this->cancellationGuard = $cancellationGuard;
    }

    /**
     * Run the video rendering pipeline for a given Surah number.
     *
     * @param int $surahNumber
     * @param string $reciterSlug
     * @param int|null $fromAyah
     * @param int|null $toAyah
     * @param string $layout
     * @param int $maxLines
     * @param bool $withTranslation
     * @param string $translationSource
     * @return string Path to the generated video.
     */
    public function render(
        int $surahNumber,
        string $reciterSlug = 'yasser-al-dosari',
        ?int $fromAyah = null,
        ?int $toAyah = null,
        string $layout = 'reels',
        int $maxLines = 1,
        bool $withTranslation = false,
        string $translationSource = 'sahih_international',
        ?\App\Modules\Quran\Models\RenderJob $renderJob = null,
        bool $useGeneratedContent = false
    ): string {
        $this->cancellationGuard->ensureNotCancelled($renderJob);

        Log::info("[RenderPipeline] Starting rendering pipeline", [
            'surah' => $surahNumber,
            'reciter' => $reciterSlug,
            'from_ayah' => $fromAyah,
            'to_ayah' => $toAyah,
            'with_translation' => $withTranslation,
            'translation_source' => $translationSource,
        ]);

        // 1. Select Surah
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new RuntimeException("Surah {$surahNumber} not found in database.");
        }

        // 2. Select Reciter
        $reciter = Reciter::where('slug', $reciterSlug)->first();
        if (!$reciter) {
            throw new RuntimeException("Reciter '{$reciterSlug}' not found in database.");
        }

        // Validate range against resolved coverage
        $coverage = $this->coverageResolver->resolve($reciter->id, $surahNumber);
        if ($coverage !== null) {
            $from = $fromAyah ?? 1;
            $to = $toAyah ?? $surah->ayahs()->max('ayah_number');

            if ($from < $coverage['from_ayah'] || $to > $coverage['to_ayah']) {
                throw new \InvalidArgumentException(
                    "The uploaded dataset covers ayahs {$coverage['from_ayah']}–{$coverage['to_ayah']} only. " .
                    "Requested range {$from}–{$to} is outside the available dataset."
                );
            }
        }

        // 3. Load AudioFile metadata
        $audioFile = AudioFile::where('reciter_id', $reciter->id)
            ->where('surah_number', $surahNumber)
            ->first();

        if (!$audioFile) {
            throw new RuntimeException("Audio file metadata not found for Surah {$surahNumber} and reciter '{$reciter->slug}'.");
        }

        $absoluteAudioPath = $this->pathResolver->audio($audioFile->file_path);
        if (!file_exists($absoluteAudioPath)) {
            throw new RuntimeException("Audio file does not exist on disk: {$absoluteAudioPath}");
        }

        $totalDuration = $audioFile->duration_ms / 1000.0;
        if ($totalDuration <= 0) {
            throw new RuntimeException("Invalid audio duration in database: {$audioFile->duration_ms} ms");
        }

        // 4. Load ordered Word stream (optionally filtered by ayah range)
        $ayahQuery = $surah->ayahs();
        if ($fromAyah !== null) {
            $ayahQuery = $ayahQuery->where('ayah_number', '>=', $fromAyah);
        }
        if ($toAyah !== null) {
            $ayahQuery = $ayahQuery->where('ayah_number', '<=', $toAyah);
        }
        $ayahIds = $ayahQuery->pluck('id');

        $words = Word::with('ayah')->whereIn('ayah_id', $ayahIds)
            ->orderBy('page_number')
            ->orderBy('line_number')
            ->orderBy('ayah_id')
            ->orderBy('word_index')
            ->get()
            ->all();

        if (empty($words)) {
            throw new RuntimeException("No words found in database for the selected ayah range.");
        }

        // Resolve and assign reciter-specific timings in-memory (never save to DB!)
        $timingMap = $this->timingResolver->resolve($reciter, $words);
        foreach ($words as $w) {
            if (isset($timingMap[$w->id])) {
                $w->start_ms_from_surah = $timingMap[$w->id]['start_ms'];
                $w->end_ms_from_surah = $timingMap[$w->id]['end_ms'];
            }
        }

        // 5. Check timings and apply proportional scheduling fallback if timings are unavailable
        $hasTimings = false;
        foreach ($words as $w) {
            if ($w->char_type === 'word' && $w->start_ms_from_surah !== null) {
                $hasTimings = true;
                break;
            }
        }

        $audioStartMs = 0;
        $audioEndMs = (int) round($totalDuration * 1000);

        if (!$hasTimings) {
            Log::info("[RenderPipeline] Timings not found in DB. Falling back to proportional scheduling.");

            $spokenWords = array_filter($words, fn($w) => $w->char_type === 'word');
            $spokenCount = count($spokenWords);

            if ($spokenCount > 0) {
                $durationPerWord = $audioEndMs / $spokenCount;

                $spokenIndex = 0;
                foreach ($words as $w) {
                    if ($w->char_type === 'word') {
                        // Modify in-memory only (do not call save())
                        $w->start_ms_from_surah = (int) round($spokenIndex * $durationPerWord);
                        $w->end_ms_from_surah = (int) round(($spokenIndex + 1) * $durationPerWord);
                        $spokenIndex++;
                    }
                }
            }
        } else {
            // Find absolute bounds for trimming
            $firstSpoken = null;
            $lastSpoken = null;
            foreach ($words as $w) {
                if ($w->char_type === 'word' && $w->start_ms_from_surah !== null) {
                    if ($firstSpoken === null || $w->start_ms_from_surah < $firstSpoken->start_ms_from_surah) {
                        $firstSpoken = $w;
                    }
                    if ($lastSpoken === null || $w->end_ms_from_surah > $lastSpoken->end_ms_from_surah) {
                        $lastSpoken = $w;
                    }
                }
            }

            if ($firstSpoken !== null && $lastSpoken !== null) {
                $audioStartMs = $firstSpoken->start_ms_from_surah;
                $audioEndMs = $lastSpoken->end_ms_from_surah;

                // Normalize timings in-memory relative to trimmed start
                foreach ($words as $w) {
                    if ($w->char_type === 'word' && $w->start_ms_from_surah !== null) {
                        // Modify in-memory only (do not call save())
                        $w->start_ms_from_surah = max(0, $w->start_ms_from_surah - $audioStartMs);
                        $w->end_ms_from_surah = max(0, $w->end_ms_from_surah - $audioStartMs);
                    }
                }
                
                // Update total duration constraint to match the trimmed segment length
                $totalDuration = ($audioEndMs - $audioStartMs) / 1000.0;
            }
        }

        // Checkpoint 1: Before segment generation
        $this->cancellationGuard->ensureNotCancelled($renderJob);

        // 6. Segment the words
        $hasApprovedGeneratedContent = false;
        $alignedRanges = [];

        if ($useGeneratedContent) {
            $resolver = app(\App\Services\ContentGeneration\ApprovedContentResolver::class);
            $approvedSegmentsData = $resolver->resolve($reciter->id, $surahNumber, $fromAyah, $toAyah, $layout);

            if ($approvedSegmentsData !== null) {
                Log::info('RENDER SOURCE', [
                    'source' => 'approved_generated',
                ]);

                try {
                    $aligner = app(\App\Services\ContentGeneration\GeneratedContentWordAligner::class);
                    $segmentBuilder = app(\App\Services\ContentGeneration\ApprovedSegmentBuilder::class);
                    
                    $alignedRanges = $aligner->align($approvedSegmentsData, $words);
                    
                    // Validate aligned ranges using ReelsSegmentValidator
                    $reelsValidator = app(\App\Services\ContentGeneration\ReelsSegmentValidator::class);
                    $reelsValidator->validate($alignedRanges);

                    $segments = $segmentBuilder->build($alignedRanges);
                    $hasApprovedGeneratedContent = true;
                    Log::info("[RenderPipeline] Loaded " . count($segments) . " segments from approved generated content.");
                } catch (\App\Services\ContentGeneration\Exceptions\MissingTimingException $e) {
                    if (app()->environment('local', 'testing')) {
                        throw $e;
                    }
                    
                    Log::warning("[RenderPipeline] Production Fallback: Missing timings detected in approved generated content. Falling back to default segmentation.", [
                        'message' => $e->getMessage(),
                        'segment_order' => $e->getSegmentOrder(),
                    ]);
                    // Fallback to default segmentation
                    $hasApprovedGeneratedContent = false;
                    $segments = $this->segmentationService->segment($words, $totalDuration, $layout, $maxLines, $reciter, $surahNumber);
                }
            } else {
                Log::info('RENDER SOURCE', [
                    'source' => 'default',
                ]);
                $segments = $this->segmentationService->segment($words, $totalDuration, $layout, $maxLines, $reciter, $surahNumber);
            }
        } else {
            Log::info('RENDER SOURCE', [
                'source' => 'default',
            ]);
            $segments = $this->segmentationService->segment($words, $totalDuration, $layout, $maxLines, $reciter, $surahNumber);
        }

        if (empty($segments)) {
            throw new RuntimeException("Segmentation service returned no segments for Surah {$surahNumber}.");
        }

        // Build Translation Layer if requested
        $translationLayer = null;
        if ($withTranslation) {
            if ($hasApprovedGeneratedContent) {
                $approvedTranslationBuilder = app(\App\Services\ContentGeneration\ApprovedTranslationSegmentBuilder::class);
                $translationSegments = $approvedTranslationBuilder->build($approvedSegmentsData, $alignedRanges, $surahNumber);
            } else {
                $translationSegments = $this->translationSegmentBuilder->build($segments, $surahNumber);
            }
            $translationLayer = new \App\Modules\Rendering\Layers\TranslationTextLayer($translationSegments);
            Log::info("[RenderPipeline] Instantiated TranslationTextLayer with " . count($translationSegments) . " segments.");
        }

        // Populate segment metrics for debug/report output
        $fontSize = ($layout === 'youtube') ? 50 : config('layouts.reels.font_size', 56);
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

        // 7. Generate debug segment JSON file
        $this->writeDebugSegmentsJson($surahNumber, $segments, $reciterSlug, $fromAyah, $toAyah, $layout);

        // Checkpoint 2: Before frame rendering starts
        $this->cancellationGuard->ensureNotCancelled($renderJob);

        // 8. Render all segments as PNGs and build frame schedule
        $composerFrames = [];
        foreach ($segments as $idx => $segment) {
            // Checkpoint 3: Periodically during frame generation (every 5 frames)
            if ($idx % 5 === 0) {
                $this->cancellationGuard->ensureNotCancelled($renderJob);
            }
            $paddedIndex = sprintf('%03d', $segment->index);
            
            $rangeDir = '';
            if ($fromAyah !== null && $toAyah !== null) {
                $rangeDir = ($fromAyah === $toAyah) ? "_ayah_{$fromAyah}" : "_from_{$fromAyah}_to_{$toAyah}";
            }
            if ($layout === 'youtube') {
                $rangeDir .= '_youtube';
            }
            $segmentPath = $this->pathResolver->renderedSegments("surah_{$surahNumber}_{$reciterSlug}{$rangeDir}/segment_{$paddedIndex}.png");

            $activeStrategy = ($layout === 'youtube') ? $this->youtubeLayoutStrategy : $this->layoutStrategy;
            $layoutData = $activeStrategy->layout($segment, ['reciter' => $reciter]);

            $translationSegment = null;
            if ($translationLayer) {
                $translationSegment = $translationLayer->getSegmentForArabic($segment);
            }

            $this->segmentRenderer->renderSegment(
                $surah,
                $segment,
                $segmentPath,
                $layoutData,
                $translationLayer,
                $translationSegment
            );

            $segmentDurationSec = ($segment->endMs - $segment->startMs) / 1000.0;

            $composerFrames[] = [
                'image_path' => $segmentPath,
                'duration' => $segmentDurationSec,
            ];
        }

        // 9. Compose the video from segments and audio
        $suffix = $reciterSlug === 'yasser-al-dosari' ? '' : "_{$reciterSlug}";
        if ($fromAyah !== null && $toAyah !== null) {
            $suffix .= ($fromAyah === $toAyah) ? "_ayah_{$fromAyah}" : "_from_{$fromAyah}_to_{$toAyah}";
        }
        if ($layout === 'youtube') {
            $suffix .= '_youtube';
        }

        $outputVideoPath = $this->pathResolver->videos("surah_{$surahNumber}{$suffix}.mp4");
        
        // Checkpoint 4: Before FFmpeg finalization
        $this->cancellationGuard->ensureNotCancelled($renderJob);

        $audioStartSec = $audioStartMs / 1000.0;
        $audioDurationSec = ($audioEndMs - $audioStartMs) / 1000.0;

        $this->videoComposer->compose(
            $composerFrames,
            $absoluteAudioPath,
            $outputVideoPath,
            $totalDuration,
            $audioStartSec,
            $audioDurationSec
        );

        Log::info("[RenderPipeline] Completed video generation for Surah {$surahNumber}");

        // Checkpoint 5: Before saving final outputs
        $this->cancellationGuard->ensureNotCancelled($renderJob);

        return $outputVideoPath;
    }

    /**
     * Write debugging segments JSON file.
     */
    protected function writeDebugSegmentsJson(
        int $surahNumber,
        array $segments,
        string $reciterSlug,
        ?int $fromAyah = null,
        ?int $toAyah = null,
        string $layout = 'reels'
    ): void {
        $suffix = $reciterSlug === 'yasser-al-dosari' ? '' : "_{$reciterSlug}";
        if ($fromAyah !== null && $toAyah !== null) {
            $suffix .= ($fromAyah === $toAyah) ? "_ayah_{$fromAyah}" : "_from_{$fromAyah}_to_{$toAyah}";
        }
        if ($layout === 'youtube') {
            $suffix .= '_youtube';
        }
        
        $debugFile = $this->pathResolver->debug("segments/surah_{$surahNumber}{$suffix}_segments.json");

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

        file_put_contents($debugFile, json_encode($debugData, JSON_PRETTY_PRINT));
        Log::info("[RenderPipeline] Written segments debug log to {$debugFile}");
    }

    /**
     * Clean up temporary frames, partial video output, and debug json logs.
     */
    public function cleanup(
        int $surahNumber,
        string $reciterSlug,
        ?int $fromAyah = null,
        ?int $toAyah = null,
        string $layout = 'reels'
    ): void {
        Log::info("[RenderPipeline] Starting cancellation cleanup for Surah {$surahNumber}, reciter {$reciterSlug}");

        $rangeDir = '';
        if ($fromAyah !== null && $toAyah !== null) {
            $rangeDir = ($fromAyah === $toAyah) ? "_ayah_{$fromAyah}" : "_from_{$fromAyah}_to_{$toAyah}";
        }
        if ($layout === 'youtube') {
            $rangeDir .= '_youtube';
        }

        // 1. Clean up temporary frame directory
        $segmentsDir = $this->pathResolver->renderedSegments("surah_{$surahNumber}_{$reciterSlug}{$rangeDir}");
        if (is_dir($segmentsDir)) {
            $files = glob($segmentsDir . '/*.png');
            if ($files) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            @rmdir($segmentsDir);
            Log::info("[RenderPipeline] Cleaned up temporary frames directory: {$segmentsDir}");
        }

        // 2. Clean up partial/final video file
        $suffix = $reciterSlug === 'yasser-al-dosari' ? '' : "_{$reciterSlug}";
        if ($fromAyah !== null && $toAyah !== null) {
            $suffix .= ($fromAyah === $toAyah) ? "_ayah_{$fromAyah}" : "_from_{$fromAyah}_to_{$toAyah}";
        }
        if ($layout === 'youtube') {
            $suffix .= '_youtube';
        }
        $outputVideoPath = $this->pathResolver->videos("surah_{$surahNumber}{$suffix}.mp4");
        if (file_exists($outputVideoPath)) {
            unlink($outputVideoPath);
            Log::info("[RenderPipeline] Cleaned up video output file: {$outputVideoPath}");
        }

        // 3. Clean up debug segments json
        $debugFile = $this->pathResolver->debug("segments/surah_{$surahNumber}{$suffix}_segments.json");
        if (file_exists($debugFile)) {
            unlink($debugFile);
            Log::info("[RenderPipeline] Cleaned up debug segments json log: {$debugFile}");
        }
    }
}
