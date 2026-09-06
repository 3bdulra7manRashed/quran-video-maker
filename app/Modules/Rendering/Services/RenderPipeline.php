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
        bool $useGeneratedContent = false,
        bool $withTafsir = false,
        string $tafsirSource = 'ar-tafsir-muyassar',
        ?string $customJson = null
    ): string {
        $this->cancellationGuard->ensureNotCancelled($renderJob);

        if ($withTranslation) {
            $exists = \App\Modules\Quran\Models\AyahTranslation::where('source', 'sahih_international')->exists();
            if (!$exists) {
                throw new \App\Modules\Rendering\Exceptions\TranslationUnavailableException(
                    "Official Sahih International translations have not been imported.\n\nRun:\n\nphp artisan import:verse-translations"
                );
            }
        }

        if ($withTafsir) {
            $hasCustomFile = $renderJob && file_exists(storage_path('app/temp/custom_render_' . $renderJob->uuid . '.json'));
            $isCustomOrGenerated = $useGeneratedContent || ($renderJob && $renderJob->use_generated_content) || ($customJson !== null && trim($customJson) !== '') || $hasCustomFile;
            if (!$isCustomOrGenerated) {
                $exists = \App\Modules\Quran\Models\AyahTranslation::where('source', $tafsirSource)->exists();
                if (!$exists) {
                    throw new \App\Modules\Rendering\Exceptions\TranslationUnavailableException(
                        "Tafsir source '{$tafsirSource}' has not been imported.\n\nRun:\n\nphp artisan quran:download-tafsir"
                    );
                }
            }
        }

        Log::info("[RenderPipeline] Starting rendering pipeline", [
            'surah' => $surahNumber,
            'reciter' => $reciterSlug,
            'from_ayah' => $fromAyah,
            'to_ayah' => $toAyah,
            'with_translation' => $withTranslation,
            'translation_source' => $translationSource,
            'with_tafsir' => $withTafsir,
            'tafsir_source' => $tafsirSource,
        ]);

        // 1. Select Surah
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new RuntimeException("Surah {$surahNumber} not found in database.");
        }

        // 2. Select Reciter
        $reciter = Reciter::findBySlug($reciterSlug);
        if (!$reciter) {
            throw new RuntimeException("Reciter '{$reciterSlug}' not found in database.");
        }
        $reciterSlug = $reciter->slug;

        // Detect and inject custom JSON timing/segment payload if present
        $parsedCustomJson = null;
        if ($customJson !== null && trim($customJson) !== '') {
            $parsedCustomJson = is_array($customJson) ? $customJson : json_decode($customJson, true);
        } elseif ($renderJob) {
            $tempFile = storage_path('app/temp/custom_render_' . $renderJob->uuid . '.json');
            if (file_exists($tempFile)) {
                $customJsonStr = file_get_contents($tempFile);
                $parsedCustomJson = json_decode($customJsonStr, true);
                @unlink($tempFile);
            }
        }

        // Fallback: If no explicit customJson was passed but useGeneratedContent is true, check if approved content has source_json
        if ($parsedCustomJson === null && ($useGeneratedContent || ($renderJob && $renderJob->use_generated_content))) {
            $existingResolver = new \App\Services\ContentGeneration\ApprovedContentResolver();
            $approvedFromDb = $existingResolver->resolve($reciter->id, $surahNumber, $fromAyah, $toAyah, $layout);
            if ($approvedFromDb !== null && !$approvedFromDb->isEmpty()) {
                $firstWithSource = $approvedFromDb->first(fn($item) => !empty($item->source_json) && (isset($item->source_json['segments']) || isset($item->source_json['lines'])));
                if ($firstWithSource) {
                    $parsedCustomJson = $firstWithSource->source_json;
                }
            }
        }

        $customSegments = null;
        $hasCustomSegmentTimings = false;
        $customTimelineMap = [];

        if (is_array($parsedCustomJson)) {
            // 1. If it contains segments (Reels generated content)
            if (isset($parsedCustomJson['segments']) && is_array($parsedCustomJson['segments']) && !empty($parsedCustomJson['segments'])) {
                $customSegments = $parsedCustomJson['segments'];
                $useGeneratedContent = true;

                // Check if custom segments have explicit timestamps
                foreach ($customSegments as $seg) {
                    if (
                        isset($seg['start']) || isset($seg['start_ms']) || isset($seg['startMs']) ||
                        isset($seg['end']) || isset($seg['end_ms']) || isset($seg['endMs']) ||
                        isset($seg['duration_ms'])
                    ) {
                        $hasCustomSegmentTimings = true;
                        break;
                    }
                }

                // Auto-enable withTafsir if any custom segment has tafsir text
                foreach ($customSegments as $seg) {
                    if (!empty($seg['tafsir'])) {
                        $withTafsir = true;
                        break;
                    }
                }

                // Auto-enable withTranslation if any custom segment has translation text
                foreach ($customSegments as $seg) {
                    if (!empty($seg['translation'])) {
                        $withTranslation = true;
                        break;
                    }
                }

                // Sort custom segments sequentially by order
                usort($customSegments, function ($a, $b) {
                    $orderA = (int)($a['order'] ?? $a['segment_order'] ?? 1);
                    $orderB = (int)($b['order'] ?? $b['segment_order'] ?? 1);
                    return $orderA <=> $orderB;
                });

                // If explicit segment timestamps are present, compute exact custom timeline map
                if ($hasCustomSegmentTimings) {
                    $numSegs = count($customSegments);
                    for ($i = 0; $i < $numSegs; $i++) {
                        $seg = $customSegments[$i];
                        $order = (int)($seg['order'] ?? $seg['segment_order'] ?? ($i + 1));

                        // Extract start timestamp
                        $rawStart = $seg['start'] ?? $seg['start_ms'] ?? $seg['startMs'] ?? null;
                        if ($rawStart === null) {
                            $segStart = ($i === 0) ? 0 : $customSegments[$i - 1]['_computed_end'];
                        } else {
                            $segStart = (is_float($rawStart) && $rawStart < 1000)
                                ? (int) round($rawStart * 1000)
                                : (int) round($rawStart);
                        }

                        // Extract end timestamp
                        $rawEnd = $seg['end'] ?? $seg['end_ms'] ?? $seg['endMs'] ?? null;
                        if ($rawEnd !== null) {
                            $segEnd = (is_float($rawEnd) && $rawEnd < 1000)
                                ? (int) round($rawEnd * 1000)
                                : (int) round($rawEnd);
                        } elseif (isset($seg['duration_ms'])) {
                            $segEnd = $segStart + (int) round($seg['duration_ms']);
                        } elseif ($i < $numSegs - 1) {
                            $nextRawStart = $customSegments[$i + 1]['start'] ?? $customSegments[$i + 1]['start_ms'] ?? $customSegments[$i + 1]['startMs'] ?? null;
                            if ($nextRawStart !== null) {
                                $segEnd = (is_float($nextRawStart) && $nextRawStart < 1000)
                                    ? (int) round($nextRawStart * 1000)
                                    : (int) round($nextRawStart);
                            } else {
                                $segEnd = $segStart + 5000;
                            }
                        } else {
                            if (isset($parsedCustomJson['end_time_ms'])) {
                                $segEnd = (int) round($parsedCustomJson['end_time_ms']);
                            } else {
                                $segEnd = $segStart + 5000;
                            }
                        }

                        if ($segEnd <= $segStart) {
                            $segEnd = $segStart + 1000;
                        }

                        $customSegments[$i]['_computed_start'] = $segStart;
                        $customSegments[$i]['_computed_end'] = $segEnd;

                        $customTimelineMap[$order] = [
                            'start_ms' => $segStart,
                            'end_ms'   => $segEnd,
                        ];
                    }
                }

                // If custom segments don't provide Quranic arabic text, backfill from existing approved records if available
                $hasCustomArabic = false;
                foreach ($customSegments as $cs) {
                    if (!empty($cs['arabic'])) {
                        $hasCustomArabic = true;
                        break;
                    }
                }

                if (!$hasCustomArabic) {
                    $existingResolver = new \App\Services\ContentGeneration\ApprovedContentResolver();
                    $existingApproved = $existingResolver->resolve($reciter->id, $surahNumber, $fromAyah, $toAyah, $layout);
                    if ($existingApproved !== null && !$existingApproved->isEmpty()) {
                        $existingByOrder = $existingApproved->keyBy('segment_order');
                        foreach ($customSegments as $k => $cs) {
                            $ord = (int)($cs['order'] ?? $cs['segment_order'] ?? ($k + 1));
                            $ex = $existingByOrder->get($ord);
                            if ($ex) {
                                if (empty($cs['arabic'])) {
                                    $customSegments[$k]['arabic'] = $ex->arabic;
                                }
                                if (!array_key_exists('translation', $cs) || $cs['translation'] === null) {
                                    $customSegments[$k]['translation'] = $ex->translation;
                                }
                                if (!array_key_exists('tafsir', $cs) || $cs['tafsir'] === null) {
                                    $customSegments[$k]['tafsir'] = $ex->tafsir;
                                }
                            }
                        }
                    }
                }

                $mockResolver = new class($customSegments, $reciter, $surahNumber) extends \App\Services\ContentGeneration\ApprovedContentResolver {
                    protected array $segments;
                    protected $reciter;
                    protected int $surahNumber;
                    public function __construct(array $segments, $reciter, int $surahNumber) {
                        $this->segments = $segments;
                        $this->reciter = $reciter;
                        $this->surahNumber = $surahNumber;
                    }
                    public function resolve(int $reciterId, int $surahNumber, ?int $fromAyah = null, ?int $toAyah = null, string $layout = 'reels'): ?\Illuminate\Support\Collection {
                        $collection = collect();
                        foreach ($this->segments as $idx => $seg) {
                            $record = new \App\Modules\Quran\Models\ReelsGeneratedContent();
                            $record->reciter_id = $reciterId;
                            $record->surah_number = $this->surahNumber;
                            $record->segment_order = (int)($seg['order'] ?? $seg['segment_order'] ?? ($idx + 1));
                            $record->arabic = $seg['arabic'] ?? '';
                            $record->translation = $seg['translation'] ?? null;
                            $record->tafsir = $seg['tafsir'] ?? null;
                            $collection->push($record);
                        }
                        return $collection;
                    }
                };
                app()->instance(\App\Services\ContentGeneration\ApprovedContentResolver::class, $mockResolver);
            }

            // 2. If it contains lines (Mushaf Line Timings)
            if (isset($parsedCustomJson['lines'])) {
                $customLineTimings = [];
                foreach ($parsedCustomJson['lines'] as $line) {
                    $timing = new \App\Modules\Quran\Models\ReciterLineTiming();
                    $timing->page_number = (int)$line['page_number'];
                    $timing->line_number = (int)$line['line_number'];
                    $timing->start_ms = (int)$line['start'];
                    $customLineTimings[$timing->page_number . ':' . $timing->line_number] = $timing;
                }
                $lineTimingResolver = app(LineTimingResolver::class);
                $lineTimingResolver->setCustomTimings($customLineTimings);
            }

            // 3. If it contains words (Manual Word Timings)
            if (isset($parsedCustomJson['words'])) {
                $wordTimingResolver = app(WordTimingResolver::class);
                $wordTimingResolver->setCustomTimings($parsedCustomJson['words']);
            }
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

        // 5. Check timings and apply proportional scheduling fallback if timings are unavailable
        if ($hasCustomSegmentTimings) {
            Log::info("[RenderPipeline] Custom segment timings detected. Enforcing absolute priority for segment timestamps (skipping proportional fallback).");

            $audioStartMs = $customSegments[0]['_computed_start'];
            $audioEndMs = end($customSegments)['_computed_end'];
            $totalDuration = ($audioEndMs - $audioStartMs) / 1000.0;

            // Only query ReciterWordTiming if granular word-level karaoke highlighting is explicitly active
            $isKaraokeActive = false;
            if ($isKaraokeActive) {
                $timingMap = $this->timingResolver->resolve($reciter, $words);
                foreach ($words as $w) {
                    if (isset($timingMap[$w->id])) {
                        $w->start_ms_from_surah = $timingMap[$w->id]['start_ms'];
                        $w->end_ms_from_surah = $timingMap[$w->id]['end_ms'];
                    }
                }
            }
        } else {
            // Resolve and assign reciter-specific timings in-memory (never save to DB!)
            $timingMap = $this->timingResolver->resolve($reciter, $words);
            foreach ($words as $w) {
                if (isset($timingMap[$w->id])) {
                    $w->start_ms_from_surah = $timingMap[$w->id]['start_ms'];
                    $w->end_ms_from_surah = $timingMap[$w->id]['end_ms'];
                }
            }

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
        }

        // Checkpoint 1: Before segment generation
        $this->cancellationGuard->ensureNotCancelled($renderJob);

        // 6. Segment the words (Priority Hierarchy: 1. Custom JSON -> 2. DB Templates -> 3. Automated Segmentation)
        $hasApprovedGeneratedContent = false;
        $alignedRanges = [];

        if ($useGeneratedContent) {
            $resolver = app(\App\Services\ContentGeneration\ApprovedContentResolver::class);
            $approvedSegmentsData = $resolver->resolve($reciter->id, $surahNumber, $fromAyah, $toAyah, $layout);

            if ($approvedSegmentsData !== null && !$approvedSegmentsData->isEmpty()) {
                Log::info('RENDER SOURCE', [
                    'source' => 'approved_generated',
                ]);

            $aligner = app(\App\Services\ContentGeneration\GeneratedContentWordAligner::class);
            $segmentBuilder = app(\App\Services\ContentGeneration\ApprovedSegmentBuilder::class);
            
            $alignedRanges = $aligner->align($approvedSegmentsData, $words);
            
            // Validate aligned ranges using ReelsSegmentValidator
            $reelsValidator = app(\App\Services\ContentGeneration\ReelsSegmentValidator::class);
            $reelsValidator->validate($alignedRanges);

            // Build the SegmentTimeline before building the segments
            if ($hasCustomSegmentTimings) {
                Log::info("[RenderPipeline] Directly constructing SegmentTimeline from custom segment timestamps (" . count($customTimelineMap) . " segments).");
                $timeline = new \App\Modules\Rendering\Services\SegmentTimeline($customTimelineMap);
            } else {
                $timelineResolver = app(\App\Modules\Rendering\Services\SegmentTimelineResolver::class);
                $timeline = $timelineResolver->resolve($alignedRanges, $audioEndMs);
            }

            $segments = $segmentBuilder->build($alignedRanges, $timeline);
            $hasApprovedGeneratedContent = true;

            // Ensure segment objects carry the custom tafsir & translation text
            foreach ($segments as $segment) {
                $genSeg = $approvedSegmentsData->firstWhere('segment_order', $segment->index);
                if ($genSeg) {
                    $segment->tafsir = $genSeg->tafsir ?? null;
                    $segment->translation = $genSeg->translation ?? null;
                }
            }

            // Clamp any word timings strictly within parent segment boundaries
            foreach ($segments as $segment) {
                if (!empty($segment->words)) {
                    foreach ($segment->words as $w) {
                        if ($w->start_ms_from_surah !== null) {
                            $w->start_ms_from_surah = max($segment->startMs, min($segment->endMs, $w->start_ms_from_surah));
                        }
                        if ($w->end_ms_from_surah !== null) {
                            $w->end_ms_from_surah = max($segment->startMs, min($segment->endMs, $w->end_ms_from_surah));
                        }
                    }
                }
            }

            Log::info("[RenderPipeline] Loaded " . count($segments) . " segments from approved generated content using SegmentTimeline.");
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
                $translationSegments = $approvedTranslationBuilder->build($approvedSegmentsData, $segments, $surahNumber);
            } else {
                $translationSegments = $this->translationSegmentBuilder->build($segments, $surahNumber);
            }
            $translationLayer = new \App\Modules\Rendering\Layers\TranslationTextLayer($translationSegments);
            Log::info("[RenderPipeline] Instantiated TranslationTextLayer with " . count($translationSegments) . " segments.");
        }

        // Build Tafsir Layer if requested
        $tafsirLayer = null;
        if ($withTafsir) {
            if ($hasApprovedGeneratedContent) {
                $tafsirSegments = [];
                foreach ($segments as $segment) {
                    $genSeg = $approvedSegmentsData->firstWhere('segment_order', $segment->index);
                    $segTafsir = $segment->tafsir ?? ($genSeg ? $genSeg->tafsir : null);
                    if ($segTafsir !== null && trim($segTafsir) !== '') {
                        $cleanTafsir = preg_replace('/<sup\b[^>]*>.*?<\/sup>/is', '', $segTafsir);
                        $cleanTafsir = strip_tags($cleanTafsir);
                        $cleanTafsir = preg_replace('/\[[^\]]*\]/', '', $cleanTafsir);
                        $cleanTafsir = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $cleanTafsir);
                        $cleanTafsir = str_replace('ـ', '', $cleanTafsir);
                        $cleanTafsir = trim(preg_replace('/\s+/u', ' ', $cleanTafsir));

                        $firstWord = !empty($segment->words) ? $segment->words[0] : null;
                        $lastWord = !empty($segment->words) ? end($segment->words) : null;
                        $fromA = ($firstWord && $firstWord->ayah) ? $firstWord->ayah->ayah_number : 1;
                        $toA = ($lastWord && $lastWord->ayah) ? $lastWord->ayah->ayah_number : 1;

                        $tafsirSegments[] = new \App\Modules\Rendering\Domain\TranslationSegment(
                            $surahNumber,
                            $cleanTafsir,
                            $fromA,
                            $toA,
                            $segment->startMs,
                            $segment->endMs
                        );
                    }
                }
                $tafsirLayer = new \App\Modules\Rendering\Layers\TafsirTextLayer($tafsirSegments);
                Log::info("[RenderPipeline] Instantiated TafsirTextLayer from approved generated content with " . count($tafsirSegments) . " segments.");
            } else {
                $repository = app(\App\Modules\Rendering\Repositories\TranslationRepository::class);
                $tafsirProvider = new class($repository, $tafsirSource) implements \App\Modules\Rendering\Contracts\TranslationProviderInterface {
                    protected $repo;
                    protected string $src;
                    public function __construct($repo, string $src) { $this->repo = $repo; $this->src = $src; }
                    public function getAyahTranslation(int $surahNumber, int $ayahNumber): string { return $this->repo->getAyahTranslation($surahNumber, $ayahNumber, $this->src); }
                    public function getAyahTranslations(int $surahNumber): array { return $this->repo->getAyahTranslations($surahNumber, $this->src); }
                    public function source(): string { return $this->src; }
                };

                $tafsirBuilder = new \App\Modules\Rendering\Services\TranslationSegmentBuilder($tafsirProvider);
                $tafsirSegments = $tafsirBuilder->build($segments, $surahNumber);
                $tafsirLayer = new \App\Modules\Rendering\Layers\TafsirTextLayer($tafsirSegments);
                Log::info("[RenderPipeline] Instantiated TafsirTextLayer with " . count($tafsirSegments) . " segments.");
            }
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

                $bbox = \imagettfbbox($fontSize, 0, $fontPath, $lineText);
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

            if (!empty($segment->tafsir)) {
                $layoutData['tafsir'] = $segment->tafsir;
                if (isset($layoutData['tafsirBounds'])) {
                    $layoutData['tafsirBounds']['tafsir'] = $segment->tafsir;
                }
            }

            if (!empty($segment->translation)) {
                $layoutData['translation'] = $segment->translation;
                if (isset($layoutData['translationBounds'])) {
                    $layoutData['translationBounds']['translation'] = $segment->translation;
                }
            }

            $translationSegment = null;
            if ($translationLayer) {
                $translationSegment = $translationLayer->getSegmentForArabic($segment);
            }

            $tafsirSegment = null;
            if ($tafsirLayer) {
                $tafsirSegment = $tafsirLayer->getSegmentForArabic($segment);
            }

            $this->segmentRenderer->renderSegment(
                $surah,
                $segment,
                $segmentPath,
                $layoutData,
                $translationLayer,
                $translationSegment,
                $tafsirLayer,
                $tafsirSegment
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
                'tafsir' => $segment->tafsir ?? null,
                'translation' => $segment->translation ?? null,
            ];
        }

        file_put_contents($debugFile, json_encode($debugData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
