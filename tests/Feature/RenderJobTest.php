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
}
