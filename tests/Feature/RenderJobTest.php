<?php

namespace Tests\Feature;

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\RenderJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderJobTest extends TestCase
{
    use RefreshDatabase;

    protected Reciter $reciter;
    protected Surah $surah;

    protected function setUp(): void
    {
        parent::setUp();

        // Create dummy reciter and surah for testing
        $this->reciter = Reciter::create([
            'name_english' => 'Test Reciter',
            'name_arabic' => 'القارئ التجريبي',
            'slug' => 'test-reciter',
        ]);

        $this->surah = Surah::create([
            'number' => 108,
            'name_arabic' => 'الكوثر',
            'name_simple' => 'Al-Kawthar',
            'verses_count' => 3,
            'start_page' => 602,
            'end_page' => 602,
        ]);
    }

    public function test_can_create_render_job_with_translation(): void
    {
        $payload = [
            'reciter' => 'test-reciter',
            'surah' => 108,
            'scope' => 'full',
            'layout' => 'reels',
            'with_translation' => true,
            'translation_source' => 'sahih_international',
        ];

        $response = $this->postJson('/api/renders', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure(['uuid', 'status']);

        $uuid = $response->json('uuid');
        $job = RenderJob::where('uuid', $uuid)->firstOrFail();

        $this->assertTrue($job->with_translation);
        $this->assertEquals('sahih_international', $job->translation_source);
    }

    public function test_can_create_render_job_without_translation_by_default(): void
    {
        $payload = [
            'reciter' => 'test-reciter',
            'surah' => 108,
            'scope' => 'full',
            'layout' => 'reels',
        ];

        $response = $this->postJson('/api/renders', $payload);

        $response->assertStatus(200);

        $uuid = $response->json('uuid');
        $job = RenderJob::where('uuid', $uuid)->firstOrFail();

        $this->assertFalse($job->with_translation);
        $this->assertNull($job->translation_source);
    }

    public function test_api_index_includes_translation_fields(): void
    {
        RenderJob::create([
            'status' => 'pending',
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'layout' => 'reels',
            'with_translation' => true,
            'translation_source' => 'sahih_international',
        ]);

        $response = $this->getJson('/api/renders');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'with_translation' => true,
            'translation_source' => 'sahih_international',
        ]);
    }

    public function test_cancel_pending_job_transitions_to_cancelled(): void
    {
        $job = RenderJob::create([
            'status' => 'pending',
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'layout' => 'reels',
        ]);

        $response = $this->postJson("/api/renders/{$job->uuid}/cancel");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'cancelled',
        ]);

        $this->assertTrue($job->fresh()->isCancelled());
    }

    public function test_cancel_processing_job_transitions_to_cancelling(): void
    {
        $job = RenderJob::create([
            'status' => 'processing',
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'layout' => 'reels',
        ]);

        $response = $this->postJson("/api/renders/{$job->uuid}/cancel");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'cancelling',
        ]);

        $this->assertTrue($job->fresh()->isCancelling());
    }

    public function test_cancel_completed_job_returns_422(): void
    {
        $job = RenderJob::create([
            'status' => 'completed',
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'layout' => 'reels',
        ]);

        $response = $this->postJson("/api/renders/{$job->uuid}/cancel");

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'status' => 'completed',
        ]);
    }

    public function test_cancel_failed_job_returns_422(): void
    {
        $job = RenderJob::create([
            'status' => 'failed',
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'layout' => 'reels',
        ]);

        $response = $this->postJson("/api/renders/{$job->uuid}/cancel");

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'status' => 'failed',
        ]);
    }

    public function test_job_exits_early_if_cancelled_while_pending(): void
    {
        $job = RenderJob::create([
            'status' => 'cancelled', // Simulating cancellation while pending
            'reciter_id' => $this->reciter->id,
            'surah_number' => $this->surah->number,
            'layout' => 'reels',
        ]);

        $pipeline = $this->createMock(\App\Modules\Rendering\Services\RenderPipeline::class);
        $pipeline->expects($this->never())->method('render'); // Ensure render is NEVER called

        $generateJob = new \App\Jobs\GenerateVideoJob($job);
        $generateJob->handle($pipeline);

        $this->assertTrue($job->fresh()->isCancelled());
    }

    public function test_import_command_populates_database_from_json(): void
    {
        // Truncate first to test fresh import
        \App\Modules\Quran\Models\AyahTranslation::truncate();

        $this->artisan('import:verse-translations')
            ->assertExitCode(0);

        $count = \App\Modules\Quran\Models\AyahTranslation::where('source', 'sahih_international')->count();
        $this->assertEquals(6236, $count);
    }

    public function test_repository_returns_official_translation_when_exists(): void
    {
        $repo = app(\App\Modules\Rendering\Contracts\TranslationRepositoryInterface::class);

        // Seed a specific translation record
        \App\Modules\Quran\Models\AyahTranslation::updateOrCreate(
            ['source' => 'sahih_international', 'surah_number' => 108, 'ayah_number' => 1],
            ['text' => 'Indeed, We have granted you, [O Muhammad], al-Kawthar.']
        );

        $translation = $repo->getAyahTranslation(108, 1, 'sahih_international');
        $this->assertEquals('Indeed, We have granted you, [O Muhammad], al-Kawthar.', $translation);
    }

    public function test_repository_falls_back_to_word_fragments_when_missing(): void
    {
        $repo = app(\App\Modules\Rendering\Contracts\TranslationRepositoryInterface::class);

        // Delete the database record for 108:2 to trigger fallback
        \App\Modules\Quran\Models\AyahTranslation::where('source', 'sahih_international')
            ->where('surah_number', 108)
            ->where('ayah_number', 2)
            ->delete();

        // Create dummy ayah and word fragments in test DB
        $ayah = \App\Modules\Quran\Models\Ayah::create([
            'surah_id' => $this->surah->id,
            'verse_key' => '108:2',
            'ayah_number' => 2,
            'page_number' => 602,
            'juz_number' => 30,
            'hizb_number' => 60,
        ]);

        \App\Modules\Quran\Models\Word::create([
            'ayah_id' => $ayah->id,
            'word_index' => 1,
            'char_type' => 'word',
            'plain_text' => 'فَصَلِّ',
            'glyph_text' => 'test1',
            'translation_en' => 'So pray',
            'page_number' => 602,
        ]);
        \App\Modules\Quran\Models\Word::create([
            'ayah_id' => $ayah->id,
            'word_index' => 2,
            'char_type' => 'word',
            'plain_text' => 'لِرَبِّكَ',
            'glyph_text' => 'test2',
            'translation_en' => 'to your Lord',
            'page_number' => 602,
        ]);
        \App\Modules\Quran\Models\Word::create([
            'ayah_id' => $ayah->id,
            'word_index' => 3,
            'char_type' => 'word',
            'plain_text' => 'وَانْحَرْ',
            'glyph_text' => 'test3',
            'translation_en' => 'and sacrifice',
            'page_number' => 602,
        ]);

        // Get translation
        $translation = $repo->getAyahTranslation(108, 2, 'sahih_international');
        
        // Assert it fell back to word fragment compilation
        $this->assertEquals('So pray to your Lord and sacrifice', $translation);
    }

    public function test_repository_strips_html_tags_and_footnotes(): void
    {
        $repo = app(\App\Modules\Rendering\Contracts\TranslationRepositoryInterface::class);

        // Seed translation record with HTML and footnotes
        \App\Modules\Quran\Models\AyahTranslation::updateOrCreate(
            ['source' => 'sahih_international', 'surah_number' => 108, 'ayah_number' => 3],
            ['text' => 'Indeed, your enemy is the one cut off.<sup foot_note=197824>1</sup> <span class="test">Test</span>']
        );

        $translation = $repo->getAyahTranslation(108, 3, 'sahih_international');
        
        // Assert HTML and <sup> footnote tag with content are cleanly stripped
        $this->assertEquals('Indeed, your enemy is the one cut off. Test', $translation);
    }

    public function test_bulk_loading_returns_correct_translations_map(): void
    {
        $repo = app(\App\Modules\Rendering\Contracts\TranslationRepositoryInterface::class);

        // Seed 108:1 and 108:2
        \App\Modules\Quran\Models\AyahTranslation::updateOrCreate(
            ['source' => 'sahih_international', 'surah_number' => 108, 'ayah_number' => 1],
            ['text' => 'Indeed, We have granted you, [O Muhammad], al-Kawthar.']
        );
        \App\Modules\Quran\Models\AyahTranslation::updateOrCreate(
            ['source' => 'sahih_international', 'surah_number' => 108, 'ayah_number' => 2],
            ['text' => 'So pray to your Lord and offer sacrifice.']
        );

        $map = $repo->getAyahTranslations(108, 'sahih_international');
        
        $this->assertArrayHasKey(1, $map);
        $this->assertArrayHasKey(2, $map);
        $this->assertEquals('Indeed, We have granted you, [O Muhammad], al-Kawthar.', $map[1]);
        $this->assertEquals('So pray to your Lord and offer sacrifice.', $map[2]);
    }
}
