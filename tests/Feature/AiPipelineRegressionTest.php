<?php

namespace Tests\Feature;

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\RenderJob;
use App\Modules\Quran\Models\ReelsGeneratedContent;
use App\Enums\LayoutType;
use App\Enums\ContentApprovalStatus;
use App\Enums\GeneratorType;
use App\Modules\Rendering\Services\RenderPipeline;
use App\Services\ContentGeneration\Exceptions\AlignmentException;
use App\Services\ContentGeneration\Exceptions\MissingTimingException;
use App\Services\ContentGeneration\ApprovedSegmentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiPipelineRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Reciter $reciter;
    protected Surah $surah;
    protected array $words;
    protected string $tempAudio;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock VideoComposer and SegmentRenderer to prevent ffmpeg/cairo execution
        $this->mock(\App\Modules\Rendering\Services\VideoComposer::class, function ($mock) {
            $mock->shouldReceive('compose')->andReturn('dummy_output.mp4');
        });

        $this->mock(\App\Modules\Rendering\Services\SegmentRenderer::class, function ($mock) {
            $mock->shouldReceive('renderSegment')->andReturn(null);
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->tempAudio) && file_exists($this->tempAudio)) {
            @unlink($this->tempAudio);
        }
        parent::tearDown();
    }

    private function seedSurahWithWords(int $surahNum): void
    {
        $this->reciter = Reciter::create([
            'slug' => 'test-reciter',
            'name_english' => 'Test Reciter',
            'name_arabic' => 'القارئ التجريبي',
        ]);

        $this->surah = Surah::create([
            'number' => $surahNum,
            'name_arabic' => 'الكوثر',
            'name_simple' => 'Al-Kawthar',
            'verses_count' => 3,
            'start_page' => 602,
            'end_page' => 602,
        ]);

        // Create audio file using path resolver
        $pathResolver = app(\App\Modules\Shared\Services\QuranPathResolver::class);
        $audioFullPath = $pathResolver->audio('test-reciter/108.mp3');
        @mkdir(dirname($audioFullPath), 0777, true);
        file_put_contents($audioFullPath, 'dummy audio data');
        $this->tempAudio = $audioFullPath;

        \App\Modules\Quran\Models\AudioFile::create([
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'file_path' => 'test-reciter/108.mp3',
            'duration_ms' => 10000,
        ]);

        // Create 3 ayahs
        $ayahs = [];
        for ($a = 1; $a <= 3; $a++) {
            $ayahs[$a] = \App\Modules\Quran\Models\Ayah::create([
                'surah_id' => $this->surah->id,
                'verse_key' => "{$surahNum}:{$a}",
                'ayah_number' => $a,
                'page_number' => 602,
                'juz_number' => 30,
                'hizb_number' => 60,
            ]);
        }

        // Create words for Surah 108
        // "إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ" (Ayah 1: 3 words)
        // "فَصَلِّ لِرَبِّكَ وَانْحَرْ" (Ayah 2: 3 words)
        // "إِنَّ شَانِئَكَ هُوَ الْأَبْتَرُ" (Ayah 3: 4 words)
        $wordData = [
            // Ayah 1
            ['ayah_id' => $ayahs[1]->id, 'word_index' => 1, 'uthmani_text' => 'إِنَّا', 'start_ms' => 100, 'end_ms' => 1000, 'line_number' => 1],
            ['ayah_id' => $ayahs[1]->id, 'word_index' => 2, 'uthmani_text' => 'أَعْطَيْنَاكَ', 'start_ms' => 1000, 'end_ms' => 2000, 'line_number' => 1],
            ['ayah_id' => $ayahs[1]->id, 'word_index' => 3, 'uthmani_text' => 'الْكَوْثَرَ', 'start_ms' => 2000, 'end_ms' => 3000, 'line_number' => 1],
            // Ayah 2
            ['ayah_id' => $ayahs[2]->id, 'word_index' => 1, 'uthmani_text' => 'فَصَلِّ', 'start_ms' => 3000, 'end_ms' => 4000, 'line_number' => 2],
            ['ayah_id' => $ayahs[2]->id, 'word_index' => 2, 'uthmani_text' => 'لِرَبِّكَ', 'start_ms' => 4000, 'end_ms' => 5000, 'line_number' => 2],
            ['ayah_id' => $ayahs[2]->id, 'word_index' => 3, 'uthmani_text' => 'وَانْحَرْ', 'start_ms' => 5000, 'end_ms' => 6000, 'line_number' => 2],
            // Ayah 3
            ['ayah_id' => $ayahs[3]->id, 'word_index' => 1, 'uthmani_text' => 'إِنَّ', 'start_ms' => 6000, 'end_ms' => 7000, 'line_number' => 3],
            ['ayah_id' => $ayahs[3]->id, 'word_index' => 2, 'uthmani_text' => 'شَانِئَكَ', 'start_ms' => 7000, 'end_ms' => 8000, 'line_number' => 3],
            ['ayah_id' => $ayahs[3]->id, 'word_index' => 3, 'uthmani_text' => 'هُوَ', 'start_ms' => 8000, 'end_ms' => 9000, 'line_number' => 3],
            ['ayah_id' => $ayahs[3]->id, 'word_index' => 4, 'uthmani_text' => 'الْأَبْتَرُ', 'start_ms' => 9000, 'end_ms' => 10000, 'line_number' => 3],
        ];

        $this->words = [];
        foreach ($wordData as $idx => $wd) {
            $this->words[] = Word::create([
                'ayah_id' => $wd['ayah_id'],
                'word_index' => $wd['word_index'],
                'char_type' => 'word',
                'uthmani_text' => $wd['uthmani_text'],
                'plain_text' => $wd['uthmani_text'],
                'glyph_text' => 'g' . ($idx + 1),
                'page_number' => 602,
                'line_number' => $wd['line_number'],
                'start_ms_from_surah' => $wd['start_ms'],
                'end_ms_from_surah' => $wd['end_ms'],
            ]);
        }

        // Seed reciter word timings
        foreach ($this->words as $idx => $word) {
            $wd = $wordData[$idx];
            \App\Modules\Quran\Models\ReciterWordTiming::create([
                'reciter_id' => $this->reciter->id,
                'word_id' => $word->id,
                'start_ms' => $wd['start_ms'],
                'end_ms' => $wd['end_ms'],
            ]);
        }
    }

    private function seedApprovedContent(): void
    {
        // Segments matching our 3 ayahs exactly
        $segments = [
            ['order' => 1, 'arabic' => 'إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ'],
            ['order' => 2, 'arabic' => 'فَصَلِّ لِرَبِّكَ وَانْحَرْ'],
            ['order' => 3, 'arabic' => 'إِنَّ شَانِئَكَ هُوَ الْأَبْتَرُ'],
        ];

        foreach ($segments as $s) {
            ReelsGeneratedContent::create([
                'reciter_id' => $this->reciter->id,
                'surah_number' => $this->surah->number,
                'start_ayah' => 1,
                'end_ayah' => 3,
                'layout_type' => LayoutType::REELS,
                'segment_order' => $s['order'],
                'arabic' => $s['arabic'],
                'translation' => 'Translation',
                'tafsir' => 'Tafsir',
                'approval_status' => ContentApprovalStatus::APPROVED,
                'content_version' => 1,
                'generator_type' => GeneratorType::GEMINI,
            ]);
        }
    }

    /**
     * Test 1: No approved content -> Default segmentation used.
     */
    public function test_default_segmentation_used_when_no_approved_content(): void
    {
        $this->seedSurahWithWords(108);

        $segmentationService = app(\App\Modules\Segmentation\Services\SegmentationService::class);
        $segmentationServiceSpy = $this->spy(\App\Modules\Segmentation\Services\SegmentationService::class);
        $segmentationServiceSpy->shouldReceive('segment')
            ->andReturnUsing(fn(...$args) => $segmentationService->segment(...$args));

        $pipeline = app(RenderPipeline::class);
        $pipeline->render(
            108,
            'test-reciter',
            1,
            3,
            'reels',
            1,
            false,
            'sahih_international',
            null,
            true // Even if we request AI pipeline, it falls back because no content exists
        );

        $segmentationServiceSpy->shouldHaveReceived('segment');
    }

    /**
     * Test 2: Approved content exists, use_generated_content=false -> Default segmentation still used.
     */
    public function test_default_segmentation_used_when_not_opting_in_despite_approved_content(): void
    {
        $this->seedSurahWithWords(108);
        $this->seedApprovedContent();

        $segmentationService = app(\App\Modules\Segmentation\Services\SegmentationService::class);
        $segmentationServiceSpy = $this->spy(\App\Modules\Segmentation\Services\SegmentationService::class);
        $segmentationServiceSpy->shouldReceive('segment')
            ->andReturnUsing(fn(...$args) => $segmentationService->segment(...$args));

        $pipeline = app(RenderPipeline::class);
        $pipeline->render(
            108,
            'test-reciter',
            1,
            3,
            'reels',
            1,
            false,
            'sahih_international',
            null,
            false // Not opted in
        );

        $segmentationServiceSpy->shouldHaveReceived('segment');
    }

    /**
     * Test 3: Approved content exists, use_generated_content=true -> Generated pipeline used.
     */
    public function test_generated_pipeline_used_when_opted_in_with_approved_content(): void
    {
        $this->seedSurahWithWords(108);
        $this->seedApprovedContent();

        $segmentationService = app(\App\Modules\Segmentation\Services\SegmentationService::class);
        $segmentationServiceSpy = $this->spy(\App\Modules\Segmentation\Services\SegmentationService::class);
        $segmentationServiceSpy->shouldReceive('segment')
            ->andReturnUsing(fn(...$args) => $segmentationService->segment(...$args));

        $pipeline = app(RenderPipeline::class);
        $pipeline->render(
            108,
            'test-reciter',
            1,
            3,
            'reels',
            1,
            false,
            'sahih_international',
            null,
            true // Opted in
        );

        $segmentationServiceSpy->shouldNotHaveReceived('segment');
    }

    /**
     * Test 7a: Missing timings in development throws MissingTimingException.
     */
    public function test_missing_timings_throws_exception_in_development(): void
    {
        $this->seedSurahWithWords(108);
        $this->seedApprovedContent();

        // Delete timing record for a word to simulate missing timing
        \App\Modules\Quran\Models\ReciterWordTiming::where('word_id', $this->words[2]->id)->delete();

        $this->expectException(MissingTimingException::class);

        $pipeline = app(RenderPipeline::class);
        $pipeline->render(
            108,
            'test-reciter',
            1,
            3,
            'reels',
            1,
            false,
            'sahih_international',
            null,
            true
        );
    }

    /**
     * Test 7b: Missing timings in production logs warning and falls back to default segmentation.
     */
    public function test_missing_timings_falls_back_in_production(): void
    {
        $this->seedSurahWithWords(108);
        $this->seedApprovedContent();

        // Delete timing record for a word to simulate missing timing
        \App\Modules\Quran\Models\ReciterWordTiming::where('word_id', $this->words[2]->id)->delete();

        // Change environment dynamically to simulate production
        $originalEnv = app()->environment();
        app()['env'] = 'production';

        $segmentationService = app(\App\Modules\Segmentation\Services\SegmentationService::class);
        $segmentationServiceSpy = $this->spy(\App\Modules\Segmentation\Services\SegmentationService::class);
        $segmentationServiceSpy->shouldReceive('segment')
            ->andReturnUsing(fn(...$args) => $segmentationService->segment(...$args));

        try {
            $pipeline = app(RenderPipeline::class);
            $pipeline->render(
                108,
                'test-reciter',
                1,
                3,
                'reels',
                1,
                false,
                'sahih_international',
                null,
                true
            );

            $segmentationServiceSpy->shouldHaveReceived('segment');
        } finally {
            // Restore original environment
            app()['env'] = $originalEnv;
        }
    }

    /**
     * Test 7c: Negative duration is prevented.
     */
    public function test_negative_duration_prevented(): void
    {
        $builder = new ApprovedSegmentBuilder();

        // Create dummy words where end timing is smaller than start timing
        $word1 = new \stdClass();
        $word1->id = 1;
        $word1->char_type = 'word';
        $word1->uthmani_text = 'test';
        $word1->start_ms_from_surah = 1000;
        $word1->end_ms_from_surah = 500; // end < start

        $alignedRanges = [
            [
                'segmentOrder' => 1,
                'startWordId' => 1,
                'endWordId' => 1,
                'wordIds' => [1],
                'words' => [$word1],
            ]
        ];

        $segments = $builder->build($alignedRanges);
        
        $this->assertEquals(1000, $segments[0]->startMs);
        $this->assertEquals(1000, $segments[0]->endMs); // adjusted endMs = startMs
        $this->assertTrue($segments[0]->endMs >= $segments[0]->startMs);
    }

    /**
     * Test 8: Reels -> must still stop at ayah boundaries exactly as before the AI pipeline existed.
     */
    public function test_reels_boundary_violation_throws_alignment_exception(): void
    {
        $this->seedSurahWithWords(108);

        // Seed a segment that crosses an ayah boundary (Ayah 1 and Ayah 2 words combined)
        ReelsGeneratedContent::create([
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'start_ayah' => 1,
            'end_ayah' => 3,
            'layout_type' => LayoutType::REELS,
            'segment_order' => 1,
            'arabic' => 'إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ فَصَلِّ لِرَبِّكَ وَانْحَرْ',
            'translation' => 'Translation',
            'tafsir' => 'Tafsir',
            'approval_status' => ContentApprovalStatus::APPROVED,
            'content_version' => 1,
            'generator_type' => GeneratorType::GEMINI,
        ]);

        $this->expectException(AlignmentException::class);
        $this->expectExceptionMessage("Reels layout regression: segment #1 crosses ayah boundaries");

        $pipeline = app(RenderPipeline::class);
        $pipeline->render(
            108,
            'test-reciter',
            1,
            3,
            'reels',
            1,
            false,
            'sahih_international',
            null,
            true
        );
    }
}
