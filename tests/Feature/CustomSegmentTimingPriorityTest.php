<?php

namespace Tests\Feature;

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Ayah;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Quran\Models\AyahTranslation;
use App\Modules\Rendering\Services\RenderPipeline;
use App\Modules\Rendering\Services\VideoComposer;
use App\Modules\Rendering\Services\SegmentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomSegmentTimingPriorityTest extends TestCase
{
    use RefreshDatabase;

    protected Reciter $yasser;
    protected Reciter $mishari;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed reciters
        $this->yasser = Reciter::create([
            'name_arabic' => 'ياسر الدوسري',
            'name_english' => 'Yasser Al-Dosari',
            'slug' => 'yasser-al-dosari',
            'is_default' => true,
        ]);

        $this->mishari = Reciter::create([
            'name_arabic' => 'مشاري راشد العفاسي',
            'name_english' => 'Mishari Rashid Al-Afasy',
            'slug' => 'mishari-al-afasy',
            'is_default' => false,
        ]);
    }

    public function test_reciter_slug_normalization_and_aliases(): void
    {
        $this->assertEquals('mishari-al-afasy', Reciter::normalizeSlug('mishari-rashid-al-afasy'));
        $this->assertEquals('mishari-al-afasy', Reciter::normalizeSlug('mishari-al-afasy'));
        $this->assertEquals('yasser-al-dosari', Reciter::normalizeSlug('yasser-aldosari'));
        $this->assertEquals('yasser-al-dosari', Reciter::normalizeSlug('yasser-al-dosari'));

        $mishari1 = Reciter::findBySlug('mishari-rashid-al-afasy');
        $this->assertNotNull($mishari1);
        $this->assertEquals($this->mishari->id, $mishari1->id);

        $mishari2 = Reciter::findBySlug('mishari-al-afasy');
        $this->assertNotNull($mishari2);
        $this->assertEquals($this->mishari->id, $mishari2->id);

        $yasser1 = Reciter::findBySlug('yasser-aldosari');
        $this->assertNotNull($yasser1);
        $this->assertEquals($this->yasser->id, $yasser1->id);

        $yasser2 = Reciter::findBySlug('yasser-al-dosari');
        $this->assertNotNull($yasser2);
        $this->assertEquals($this->yasser->id, $yasser2->id);
    }

    public function test_render_api_accepts_reciter_aliases(): void
    {
        Queue::fake();

        $surah = Surah::create([
            'number' => 108,
            'name_arabic' => 'الكوثر',
            'name_simple' => 'Al-Kawthar',
            'verses_count' => 3,
            'start_page' => 602,
            'end_page' => 602,
        ]);

        // 1. Post with mishari-rashid-al-afasy
        $response1 = $this->postJson('/api/renders', [
            'reciter' => 'mishari-rashid-al-afasy',
            'surah' => 108,
            'scope' => 'full',
        ]);
        $response1->assertStatus(200);
        $uuid1 = $response1->json('uuid');
        $job1 = \App\Modules\Quran\Models\RenderJob::where('uuid', $uuid1)->first();
        $this->assertNotNull($job1);
        $this->assertEquals($this->mishari->id, $job1->reciter_id);

        // 2. Post with yasser-aldosari
        $response2 = $this->postJson('/api/renders', [
            'reciter' => 'yasser-aldosari',
            'surah' => 108,
            'scope' => 'full',
        ]);
        $response2->assertStatus(200);
        $uuid2 = $response2->json('uuid');
        $job2 = \App\Modules\Quran\Models\RenderJob::where('uuid', $uuid2)->first();
        $this->assertNotNull($job2);
        $this->assertEquals($this->yasser->id, $job2->reciter_id);
    }

    public function test_custom_segment_timings_override_timeline_and_skip_proportional_fallback(): void
    {
        $surah = Surah::create([
            'number' => 108,
            'name_arabic' => 'الكوثر',
            'name_simple' => 'Al-Kawthar',
            'verses_count' => 3,
            'start_page' => 602,
            'end_page' => 602,
        ]);

        $reciter = Reciter::findBySlug('mishari-rashid-al-afasy');

        // Create dummy audio file
        $pathResolver = app(\App\Modules\Shared\Services\QuranPathResolver::class);
        $audioFullPath = $pathResolver->audio('mishari-al-afasy/108.mp3');
        @mkdir(dirname($audioFullPath), 0777, true);
        file_put_contents($audioFullPath, 'dummy audio data');

        AudioFile::create([
            'reciter_id' => $reciter->id,
            'surah_number' => 108,
            'file_path' => 'mishari-al-afasy/108.mp3',
            'duration_ms' => 30000,
        ]);

        // Create translations
        AyahTranslation::create([
            'source' => 'sahih_international',
            'surah_number' => 108,
            'ayah_number' => 1,
            'text' => 'Indeed, We have granted you, [O Muhammad], al-Kawthar.',
        ]);
        AyahTranslation::create([
            'source' => 'sahih_international',
            'surah_number' => 108,
            'ayah_number' => 2,
            'text' => 'So pray to your Lord and sacrifice [to Him alone].',
        ]);

        // Create ayahs & words
        $ayah1 = Ayah::create(['surah_id' => $surah->id, 'verse_key' => '108:1', 'ayah_number' => 1, 'page_number' => 602, 'juz_number' => 30, 'hizb_number' => 60]);
        $ayah2 = Ayah::create(['surah_id' => $surah->id, 'verse_key' => '108:2', 'ayah_number' => 2, 'page_number' => 602, 'juz_number' => 30, 'hizb_number' => 60]);

        Word::create(['ayah_id' => $ayah1->id, 'word_index' => 1, 'uthmani_text' => 'إِنَّآ', 'glyph_text' => 'glyph1', 'page_number' => 602, 'line_number' => 1, 'char_type' => 'word', 'audio_url' => '']);
        Word::create(['ayah_id' => $ayah1->id, 'word_index' => 2, 'uthmani_text' => 'أَعْطَيْنَـٰكَ', 'glyph_text' => 'glyph2', 'page_number' => 602, 'line_number' => 1, 'char_type' => 'word', 'audio_url' => '']);
        Word::create(['ayah_id' => $ayah1->id, 'word_index' => 3, 'uthmani_text' => 'ٱلْكَوْثَرَ', 'glyph_text' => 'glyph3', 'page_number' => 602, 'line_number' => 1, 'char_type' => 'word', 'audio_url' => '']);
        Word::create(['ayah_id' => $ayah1->id, 'word_index' => 4, 'uthmani_text' => '١', 'glyph_text' => 'glyph4', 'page_number' => 602, 'line_number' => 1, 'char_type' => 'end', 'audio_url' => '']);

        Word::create(['ayah_id' => $ayah2->id, 'word_index' => 1, 'uthmani_text' => 'فَصَلِّ', 'glyph_text' => 'glyph5', 'page_number' => 602, 'line_number' => 1, 'char_type' => 'word', 'audio_url' => '']);
        Word::create(['ayah_id' => $ayah2->id, 'word_index' => 2, 'uthmani_text' => 'لِرَبِّكَ', 'glyph_text' => 'glyph6', 'page_number' => 602, 'line_number' => 1, 'char_type' => 'word', 'audio_url' => '']);
        Word::create(['ayah_id' => $ayah2->id, 'word_index' => 3, 'uthmani_text' => 'وَٱنْحَرْ', 'glyph_text' => 'glyph7', 'page_number' => 602, 'line_number' => 1, 'char_type' => 'word', 'audio_url' => '']);
        Word::create(['ayah_id' => $ayah2->id, 'word_index' => 4, 'uthmani_text' => '٢', 'glyph_text' => 'glyph8', 'page_number' => 602, 'line_number' => 1, 'char_type' => 'end', 'audio_url' => '']);

        // Custom JSON with explicit segment timings
        $customJson = json_encode([
            'surah' => 108,
            'segments' => [
                [
                    'order' => 1,
                    'start' => 0,
                    'end' => 4000,
                    'arabic' => 'إِنَّآ أَعْطَيْنَـٰكَ ٱلْكَوْثَرَ ١',
                    'translation' => 'Indeed, We have granted you al-Kawthar',
                    'tafsir' => 'نهر في الجنة',
                ],
                [
                    'order' => 2,
                    'start' => 4000,
                    'end' => 9500,
                    'arabic' => 'فَصَلِّ لِرَبِّكَ وَٱنْحَرْ ٢',
                    'translation' => 'So pray to your Lord and sacrifice',
                    'tafsir' => 'صل لربك صلاة العيد وانحر نسكك',
                ],
            ],
            'end_time_ms' => 9500,
        ]);

        // Capture composer frames
        $capturedFrames = null;
        $this->mock(VideoComposer::class, function ($mock) use (&$capturedFrames) {
            $mock->shouldReceive('compose')
                ->once()
                ->andReturnUsing(function ($frames, $audioPath, $outputPath, $totalDuration, $audioStartSec, $audioDurationSec) use (&$capturedFrames) {
                    $capturedFrames = $frames;
                    return 'output.mp4';
                });
        });

        $renderedSegments = [];
        $this->mock(SegmentRenderer::class, function ($mock) use (&$renderedSegments) {
            $mock->shouldReceive('renderSegment')
                ->twice()
                ->andReturnUsing(function ($surah, $segment, $outputPath, $layoutData, $translationLayer, $transSeg, $tafsirLayer, $tafsirSeg) use (&$renderedSegments) {
                    $renderedSegments[] = [
                        'index' => $segment->index,
                        'startMs' => $segment->startMs,
                        'endMs' => $segment->endMs,
                        'translation' => $segment->translation,
                        'tafsir' => $segment->tafsir,
                    ];
                });
        });

        Log::spy();

        $pipeline = app(RenderPipeline::class);
        $output = $pipeline->render(
            108,
            'mishari-rashid-al-afasy',
            1,
            2,
            'reels',
            1,
            true, // with translation
            'sahih_international',
            null, // no renderJob
            false,
            true, // with tafsir
            'ar-tafsir-muyassar',
            $customJson // customJson passed directly!
        );

        // Verify proportional fallback was NOT logged
        Log::shouldNotHaveReceived('info', function ($message) {
            return str_contains($message, 'Falling back to proportional scheduling');
        });

        // Verify direct custom timeline construction was logged
        Log::shouldHaveReceived('info', function ($message) {
            return str_contains($message, 'Custom segment timings detected') || str_contains($message, 'Directly constructing SegmentTimeline from custom segment timestamps');
        });

        // Verify captured frame durations match custom segment timestamps exactly
        $this->assertNotNull($capturedFrames);
        $this->assertCount(2, $capturedFrames);
        $this->assertEquals(4.0, $capturedFrames[0]['duration']); // 4000ms - 0ms = 4.0s
        $this->assertEquals(5.5, $capturedFrames[1]['duration']); // 9500ms - 4000ms = 5.5s

        // Verify active segment texts were passed
        $this->assertCount(2, $renderedSegments);
        $this->assertEquals('Indeed, We have granted you al-Kawthar', $renderedSegments[0]['translation']);
        $this->assertEquals('نهر في الجنة', $renderedSegments[0]['tafsir']);
        $this->assertEquals(0, $renderedSegments[0]['startMs']);
        $this->assertEquals(4000, $renderedSegments[0]['endMs']);

        $this->assertEquals('So pray to your Lord and sacrifice', $renderedSegments[1]['translation']);
        $this->assertEquals('صل لربك صلاة العيد وانحر نسكك', $renderedSegments[1]['tafsir']);
        $this->assertEquals(4000, $renderedSegments[1]['startMs']);
        $this->assertEquals(9500, $renderedSegments[1]['endMs']);

        @unlink($audioFullPath);
    }
}
