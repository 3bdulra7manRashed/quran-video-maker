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
     * Test 7a: Missing timings does not throw exception and successfully repairs bounds.
     */
    public function test_missing_timings_does_not_throw_exception_and_repairs_bounds(): void
    {
        $this->seedSurahWithWords(108);
        $this->seedApprovedContent();

        // Delete timing record for a word to simulate missing timing
        \App\Modules\Quran\Models\ReciterWordTiming::where('word_id', $this->words[2]->id)->delete();

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

        $this->assertTrue(true);
    }

    /**
     * Test 7b: Missing timings in production continues using generated content with repaired timings.
     */
    public function test_missing_timings_does_not_fallback_in_production(): void
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

            // Generated pipeline should remain active, so default segmentation is NOT called
            $segmentationServiceSpy->shouldNotHaveReceived('segment');
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
        
        $this->assertEquals(0, $segments[0]->startMs);
        $this->assertEquals(1000, $segments[0]->endMs); // adjusted endMs = original startMs
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

    /**
     * Test 9: Render Surah 54 (Ayahs 9-10) with Reels layout and Generated Content enabled
     * verifying that trailing empty-normalized words do not cause reels boundary violations or alignment exceptions.
     */
    public function test_render_surah_54_with_generated_content_reels(): void
    {
        $surahNum = 54;
        $this->reciter = Reciter::create([
            'slug' => 'test-reciter',
            'name_english' => 'Test Reciter',
            'name_arabic' => 'القارئ التجريبي',
        ]);

        $this->surah = Surah::create([
            'number' => $surahNum,
            'name_arabic' => 'القمر',
            'name_simple' => 'Al-Qamar',
            'verses_count' => 55,
            'start_page' => 528,
            'end_page' => 531,
        ]);

        // Create audio file
        $pathResolver = app(\App\Modules\Shared\Services\QuranPathResolver::class);
        $audioFullPath = $pathResolver->audio("test-reciter/{$surahNum}.mp3");
        @mkdir(dirname($audioFullPath), 0777, true);
        file_put_contents($audioFullPath, 'dummy audio data');
        $this->tempAudio = $audioFullPath;

        \App\Modules\Quran\Models\AudioFile::create([
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'file_path' => "test-reciter/{$surahNum}.mp3",
            'duration_ms' => 60000,
        ]);

        // Create ayahs 9 to 10
        $ayahs = [];
        for ($a = 9; $a <= 10; $a++) {
            $ayahs[$a] = \App\Modules\Quran\Models\Ayah::create([
                'surah_id' => $this->surah->id,
                'verse_key' => "{$surahNum}:{$a}",
                'ayah_number' => $a,
                'page_number' => 529,
                'juz_number' => 27,
                'hizb_number' => 54,
            ]);
        }

        $wordData = [
            // Ayah 9
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 1, 'uthmani_text' => 'كَذَّبَتْ', 'line_number' => 1],
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 2, 'uthmani_text' => 'قَبْلَهُمْ', 'line_number' => 1],
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 3, 'uthmani_text' => 'قَوْمُ', 'line_number' => 1],
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 4, 'uthmani_text' => 'نُوحٍۢ', 'line_number' => 1],
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 5, 'uthmani_text' => 'فَكَذَّبُوا۟', 'line_number' => 1],
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 6, 'uthmani_text' => 'عَبْدَنَا', 'line_number' => 1],
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 7, 'uthmani_text' => 'وَقَالُوا۟', 'line_number' => 1],
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 8, 'uthmani_text' => 'مَجْنُونٌۭ', 'line_number' => 1],
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 9, 'uthmani_text' => 'وَٱزْدُجِرَ', 'line_number' => 1],
            ['ayah_id' => $ayahs[9]->id, 'word_index' => 10, 'uthmani_text' => '٩', 'line_number' => 1],
            // Ayah 10
            ['ayah_id' => $ayahs[10]->id, 'word_index' => 1, 'uthmani_text' => 'فَدَعَا', 'line_number' => 2],
            ['ayah_id' => $ayahs[10]->id, 'word_index' => 2, 'uthmani_text' => 'رَبَّهُۥٓ', 'line_number' => 2],
            ['ayah_id' => $ayahs[10]->id, 'word_index' => 3, 'uthmani_text' => 'أَنِّى', 'line_number' => 2],
            ['ayah_id' => $ayahs[10]->id, 'word_index' => 4, 'uthmani_text' => 'مَغْلُوبٌۭ', 'line_number' => 2],
            ['ayah_id' => $ayahs[10]->id, 'word_index' => 5, 'uthmani_text' => 'فَٱنتَصِرْ', 'line_number' => 2],
            ['ayah_id' => $ayahs[10]->id, 'word_index' => 6, 'uthmani_text' => '١٠', 'line_number' => 2],
        ];

        $words = [];
        foreach ($wordData as $idx => $wd) {
            $word = Word::create([
                'ayah_id' => $wd['ayah_id'],
                'word_index' => $wd['word_index'],
                'char_type' => 'word',
                'uthmani_text' => $wd['uthmani_text'],
                'plain_text' => $wd['uthmani_text'],
                'glyph_text' => 'g' . ($idx + 1),
                'page_number' => 529,
                'line_number' => $wd['line_number'],
            ]);
            $words[] = $word;

            \App\Modules\Quran\Models\ReciterWordTiming::create([
                'reciter_id' => $this->reciter->id,
                'word_id' => $word->id,
                'start_ms' => $idx * 1000,
                'end_ms' => ($idx + 1) * 1000,
            ]);
        }

        // Create reels generated content
        ReelsGeneratedContent::create([
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'start_ayah' => 9,
            'end_ayah' => 10,
            'layout_type' => LayoutType::REELS,
            'segment_order' => 1,
            'arabic' => 'كذبت قبلهم قوم نوحۢ',
            'translation' => 'Translation',
            'tafsir' => 'Tafsir',
            'approval_status' => ContentApprovalStatus::APPROVED,
            'content_version' => 1,
            'generator_type' => GeneratorType::GEMINI,
        ]);

        ReelsGeneratedContent::create([
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'start_ayah' => 9,
            'end_ayah' => 10,
            'layout_type' => LayoutType::REELS,
            'segment_order' => 2,
            'arabic' => 'فكذبوا۟ عبدنا وقالوا۟ مجنونۭ وٱزدجر ٩',
            'translation' => 'Translation',
            'tafsir' => 'Tafsir',
            'approval_status' => ContentApprovalStatus::APPROVED,
            'content_version' => 1,
            'generator_type' => GeneratorType::GEMINI,
        ]);

        ReelsGeneratedContent::create([
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'start_ayah' => 9,
            'end_ayah' => 10,
            'layout_type' => LayoutType::REELS,
            'segment_order' => 3,
            'arabic' => 'فدعا ربهۥٓ اني مغلوبۭ فٱنتصر ١٠',
            'translation' => 'Translation',
            'tafsir' => 'Tafsir',
            'approval_status' => ContentApprovalStatus::APPROVED,
            'content_version' => 1,
            'generator_type' => GeneratorType::GEMINI,
        ]);

        $pipeline = app(RenderPipeline::class);
        $pipeline->render(
            $surahNum,
            'test-reciter',
            9,
            10,
            'reels',
            1,
            false,
            'sahih_international',
            null,
            true
        );

        $this->assertTrue(true);
    }

    /**
     * Test 7d: Consecutive missing timing segments are interpolated proportionally based on word counts.
     */
    public function test_consecutive_missing_timings_interpolation(): void
    {
        $builder = new ApprovedSegmentBuilder();

        $word1 = new \stdClass();
        $word1->id = 1;
        $word1->char_type = 'word';
        $word1->uthmani_text = 'w1';
        $word1->start_ms_from_surah = 0;
        $word1->end_ms_from_surah = 1000;

        $word2 = new \stdClass();
        $word2->id = 2;
        $word2->char_type = 'word';
        $word2->uthmani_text = 'w2';
        $word2->start_ms_from_surah = null;
        $word2->end_ms_from_surah = null;

        $word3 = new \stdClass();
        $word3->id = 3;
        $word3->char_type = 'word';
        $word3->uthmani_text = 'w3';
        $word3->start_ms_from_surah = null;
        $word3->end_ms_from_surah = null;

        $word4 = new \stdClass();
        $word4->id = 4;
        $word4->char_type = 'word';
        $word4->uthmani_text = 'w4';
        $word4->start_ms_from_surah = null;
        $word4->end_ms_from_surah = null;

        $word5 = new \stdClass();
        $word5->id = 5;
        $word5->char_type = 'word';
        $word5->uthmani_text = 'w5';
        $word5->start_ms_from_surah = 5000;
        $word5->end_ms_from_surah = 6000;

        $alignedRanges = [
            [
                'segmentOrder' => 1,
                'startWordId' => 1,
                'endWordId' => 1,
                'wordIds' => [1],
                'words' => [$word1],
            ],
            [
                'segmentOrder' => 2,
                'startWordId' => 2,
                'endWordId' => 2,
                'wordIds' => [2],
                'words' => [$word2, clone $word2], // 2 words
            ],
            [
                'segmentOrder' => 3,
                'startWordId' => 3,
                'endWordId' => 3,
                'wordIds' => [3],
                'words' => [$word3, clone $word3, clone $word3], // 3 words
            ],
            [
                'segmentOrder' => 4,
                'startWordId' => 4,
                'endWordId' => 4,
                'wordIds' => [4],
                'words' => [$word4, clone $word4, clone $word4, clone $word4, clone $word4], // 5 words
            ],
            [
                'segmentOrder' => 5,
                'startWordId' => 5,
                'endWordId' => 5,
                'wordIds' => [5],
                'words' => [$word5],
            ],
        ];

        $segments = $builder->build($alignedRanges, 6000);

        $this->assertCount(5, $segments);

        // Seg 1 keeps its end time
        $this->assertEquals(0, $segments[0]->startMs);
        $this->assertEquals(1000, $segments[0]->endMs);

        // Gap duration to distribute: 5000 - 1000 = 4000 ms
        // Total words in cluster (Segs 2, 3, 4): 2 + 3 + 5 = 10 words.
        // Seg 2 gets: 34 + (4000 - 102) * (2 / 10) = 34 + 3898 * 0.2 = 34 + 780 = 814 ms.
        // Seg 3 gets: 34 + (4000 - 102) * (3 / 10) = 34 + 3898 * 0.3 = 34 + 1169 = 1203 ms.
        // Seg 4 gets the rest to reach 5000.
        
        $this->assertEquals(1000, $segments[1]->startMs);
        $this->assertEquals(1814, $segments[1]->endMs);
        $this->assertEquals(814, $segments[1]->durationMs);

        $this->assertEquals(1814, $segments[2]->startMs);
        $this->assertEquals(3017, $segments[2]->endMs);
        $this->assertEquals(1203, $segments[2]->durationMs);

        $this->assertEquals(3017, $segments[3]->startMs);
        $this->assertEquals(5000, $segments[3]->endMs);
        $this->assertEquals(1983, $segments[3]->durationMs);

        $this->assertEquals(5000, $segments[4]->startMs);
        $this->assertEquals(6000, $segments[4]->endMs);
    }

    /**
     * Test 7e: PromptBuilder appends the strict output format instructions at the end of the prompt.
     */
    public function test_prompt_builder_strict_output_format_instructions(): void
    {
        $this->seedSurahWithWords(108);

        $builder = app(\App\Services\ContentGeneration\PromptBuilder::class);
        $prompt = $builder->build(108, 'test-reciter');

        $this->assertStringContainsString('## Output Format (STRICT)', $prompt);
        $this->assertStringContainsString('Return your answer as a single valid JSON object wrapped inside exactly one Markdown JSON code block.', $prompt);
        $this->assertStringContainsString('All JSON keys and string values must use double quotes', $prompt);
        
        $expectedSuffix = "If any check fails, regenerate the entire response until it is valid.";
        $this->assertEquals($expectedSuffix, substr(trim($prompt), -strlen($expectedSuffix)));
    }

    /**
     * Test 7i: PromptBuilder only uses Sahih International, leaves missing ones empty, and never falls back to literal.
     */
    public function test_prompt_builder_only_uses_sahih_international_without_literal_fallback(): void
    {
        $this->seedSurahWithWords(108);

        // 1. Seed Sahih International for Ayah 1 and 3, leaving Ayah 2 missing
        \App\Modules\Quran\Models\AyahTranslation::updateOrCreate(
            ['source' => 'sahih_international', 'surah_number' => 108, 'ayah_number' => 1],
            ['text' => 'Indeed, We have granted you, [O Muhammad], al-Kawthar.']
        );
        \App\Modules\Quran\Models\AyahTranslation::updateOrCreate(
            ['source' => 'sahih_international', 'surah_number' => 108, 'ayah_number' => 3],
            ['text' => 'Indeed, your enemy is the one cut off.']
        );

        // Ensure Ayah 2 translation is missing from AyahTranslation
        \App\Modules\Quran\Models\AyahTranslation::where('source', 'sahih_international')
            ->where('surah_number', 108)
            ->where('ayah_number', 2)
            ->delete();

        // 2. Set word-level literal translation (translation_en) on words of Ayah 2
        // to verify that PromptBuilder completely ignores it
        \App\Modules\Quran\Models\Word::whereHas('ayah', function ($q) {
            $q->where('surah_id', $this->surah->id)->where('ayah_number', 2);
        })->update(['translation_en' => 'literal_translation_text']);

        $builder = app(\App\Services\ContentGeneration\PromptBuilder::class);
        $prompt = $builder->build(108, 'test-reciter');

        // 3. Verify Sahih International is populated
        $this->assertStringContainsString('1: Indeed, We have granted you, [O Muhammad], al-Kawthar.', $prompt);
        $this->assertStringContainsString('3: Indeed, your enemy is the one cut off.', $prompt);

        // 4. Verify missing Sahih International for Ayah 2 is left empty
        $this->assertStringContainsString("2: \n", $prompt);

        // 5. Verify Literal Translation is NOT substituted or included anywhere in the prompt
        $this->assertStringNotContainsString('literal_translation_text', $prompt);
    }

    /**
     * Test 7f: MushafLineTimingPromptBuilder builds correct prompt.
     */
    public function test_mushaf_line_timing_prompt_builder(): void
    {
        $builder = new \App\Services\ContentGeneration\MushafLineTimingPromptBuilder();
        $prompt = $builder->build(
            76,
            1,
            4,
            578,
            12,
            "0:00.000\n0:12.034\n0:27.177\n0:36.596"
        );

        $this->assertStringContainsString('Surah: 76', $prompt);
        $this->assertStringContainsString('From Ayah: 1', $prompt);
        $this->assertStringContainsString('To Ayah: 4', $prompt);
        $this->assertStringContainsString('Start Page: 578', $prompt);
        $this->assertStringContainsString('Start Line: 12', $prompt);
        $this->assertStringContainsString("0:00.000\n0:12.034\n0:27.177\n0:36.596", $prompt);
        $this->assertStringContainsString('"type": "mushaf_lines"', $prompt);
        $this->assertStringContainsString('Mapping Rules', $prompt);
    }

    /**
     * Test 7g: POST /api/datasets/generate-mushaf-prompt returns generated prompt.
     */
    public function test_generate_mushaf_prompt_api_endpoint(): void
    {
        $response = $this->postJson('/api/datasets/generate-mushaf-prompt', [
            'surah' => 76,
            'from_ayah' => 1,
            'to_ayah' => 4,
            'start_page' => 578,
            'start_line' => 12,
            'markers' => "0:00.000\n0:12.034\n0:27.177\n0:36.596"
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['prompt']);
        $prompt = $response->json('prompt');
        $this->assertStringContainsString('Surah: 76', $prompt);
        $this->assertStringContainsString("0:00.000\n0:12.034\n0:27.177\n0:36.596", $prompt);
    }

    /**
     * Test 7h: POST /api/renders with custom_json overrides approved content resolver.
     */
    public function test_render_with_custom_json_override(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $reciter = Reciter::create([
            'slug' => 'test-reciter-custom-json',
            'name_english' => 'Test Reciter Custom',
            'name_arabic' => 'القارئ التجريبي المخصص',
        ]);
        $surah = Surah::create([
            'number' => 77,
            'name_english' => 'Al-Mursalat',
            'name_simple' => 'Al-Mursalat',
            'name_arabic' => 'المرسلات',
            'verses_count' => 50,
            'start_page' => 580,
            'end_page' => 581,
        ]);

        $customJson = json_encode([
            'segments' => [
                [
                    'order' => 1,
                    'arabic' => 'بسم الله',
                    'translation' => 'In the name of Allah',
                    'tafsir' => 'Tafsir text',
                ]
            ]
        ]);

        $response = $this->postJson('/api/renders', [
            'reciter' => $reciter->slug,
            'surah' => $surah->number,
            'scope' => 'full',
            'custom_json' => $customJson,
        ]);

        $response->assertStatus(200);
        $uuid = $response->json('uuid');
        $this->assertNotEmpty($uuid);

        $tempFile = storage_path('app/temp/custom_render_' . $uuid . '.json');
        $this->assertFileExists($tempFile);
        $this->assertEquals($customJson, file_get_contents($tempFile));

        @unlink($tempFile);
    }

    /**
     * Test: JSON file missing -> throws TranslationUnavailableException
     */
    public function test_translation_rendering_fails_when_json_file_missing(): void
    {
        $this->seedSurahWithWords(108);

        // Delete/move JSON translation file if it exists in test environment
        $jsonPath = storage_path('app/quran/translations/sahih_international.json');
        $tempPath = storage_path('app/quran/translations/sahih_international.json.bak');
        if (file_exists($jsonPath)) {
            @rename($jsonPath, $tempPath);
        }

        try {
            $pipeline = app(RenderPipeline::class);
            $pipeline->render(
                108,
                'test-reciter',
                1,
                3,
                'reels',
                1,
                true, // with_translation = true
                'sahih_international'
            );
            $this->fail("Expected TranslationUnavailableException was not thrown.");
        } catch (\App\Modules\Rendering\Exceptions\TranslationUnavailableException $e) {
            $this->assertStringContainsString('Missing translation dataset', $e->getMessage());
            $this->assertStringContainsString('storage/app/quran/translations/sahih_international.json', $e->getMessage());
        } finally {
            if (file_exists($tempPath)) {
                @rename($tempPath, $jsonPath);
            }
        }
    }

    /**
     * Test: JSON exists but database empty -> throws TranslationUnavailableException
     */
    public function test_translation_rendering_fails_when_database_table_empty(): void
    {
        $this->seedSurahWithWords(108);

        // Clear translations table
        \App\Modules\Quran\Models\AyahTranslation::truncate();

        // Ensure JSON file exists
        $jsonPath = storage_path('app/quran/translations/sahih_international.json');
        @mkdir(dirname($jsonPath), 0777, true);
        file_put_contents($jsonPath, '[]');

        try {
            $pipeline = app(RenderPipeline::class);
            $pipeline->render(
                108,
                'test-reciter',
                1,
                3,
                'reels',
                1,
                true, // with_translation = true
                'sahih_international'
            );
            $this->fail("Expected TranslationUnavailableException was not thrown.");
        } catch (\App\Modules\Rendering\Exceptions\TranslationUnavailableException $e) {
            $this->assertStringContainsString('Official Sahih International translations have not been imported', $e->getMessage());
            $this->assertStringContainsString('php artisan import:verse-translations', $e->getMessage());
        } finally {
            @unlink($jsonPath);
        }
    }

    /**
     * Test: Database populated correctly -> rendering proceeds without throwing.
     */
    public function test_translation_rendering_succeeds_when_database_populated(): void
    {
        $this->seedSurahWithWords(108);

        // Populate database
        \App\Modules\Quran\Models\AyahTranslation::updateOrCreate(
            ['source' => 'sahih_international', 'surah_number' => 108, 'ayah_number' => 1],
            ['text' => 'Translation 1']
        );
        \App\Modules\Quran\Models\AyahTranslation::updateOrCreate(
            ['source' => 'sahih_international', 'surah_number' => 108, 'ayah_number' => 2],
            ['text' => 'Translation 2']
        );
        \App\Modules\Quran\Models\AyahTranslation::updateOrCreate(
            ['source' => 'sahih_international', 'surah_number' => 108, 'ayah_number' => 3],
            ['text' => 'Translation 3']
        );

        // Ensure JSON file exists
        $jsonPath = storage_path('app/quran/translations/sahih_international.json');
        @mkdir(dirname($jsonPath), 0777, true);
        file_put_contents($jsonPath, '[]');

        try {
            $pipeline = app(RenderPipeline::class);
            $pipeline->render(
                108,
                'test-reciter',
                1,
                3,
                'reels',
                1,
                true, // with_translation = true
                'sahih_international'
            );
            $this->assertTrue(true); // Rendering completed successfully without throwing exception
        } finally {
            @unlink($jsonPath);
        }
    }

    /**
     * Test: Controller returns 422 JSON response with error message when translations are not imported
     */
    public function test_api_render_endpoint_returns_422_when_translation_unavailable(): void
    {
        $this->seedSurahWithWords(108);

        // Clear translations table
        \App\Modules\Quran\Models\AyahTranslation::truncate();

        // Ensure JSON file exists
        $jsonPath = storage_path('app/quran/translations/sahih_international.json');
        @mkdir(dirname($jsonPath), 0777, true);
        file_put_contents($jsonPath, '[]');

        try {
            $response = $this->postJson('/api/renders', [
                'reciter' => 'test-reciter',
                'surah' => 108,
                'scope' => 'full',
                'with_translation' => true,
                'translation_source' => 'sahih_international',
            ]);

            $response->assertStatus(422);
            $response->assertJsonFragment([
                'error' => "Official Sahih International translations have not been imported.\n\nRun:\n\nphp artisan import:verse-translations"
            ]);
        } finally {
            @unlink($jsonPath);
        }
    }
}
