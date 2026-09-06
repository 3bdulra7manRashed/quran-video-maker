<?php

namespace Tests\Feature;

use App\Modules\Layout\Strategies\ReelSingleLineLayoutStrategy;
use App\Modules\Layout\Strategies\YouTubeMushafLayoutStrategy;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\RenderJob;
use App\Modules\Quran\Models\Surah;
use App\Modules\Segmentation\DTO\Segment;
use Database\Seeders\ReciterSeeder;
use Database\Seeders\SurahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuranMeThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReciterSeeder::class);
        $this->seed(SurahSeeder::class);
    }

    public function test_api_creates_render_job_with_quran_me_theme(): void
    {
        $response = $this->postJson('/api/renders', [
            'reciter' => 'yasser-al-dosari',
            'surah' => 108,
            'scope' => 'single',
            'ayah' => 1,
            'layout' => 'reels',
            'theme' => 'quran_me',
        ]);

        $response->assertStatus(200);
        $uuid = $response->json('uuid');

        $this->assertDatabaseHas('render_jobs', [
            'uuid' => $uuid,
            'theme' => 'quran_me',
        ]);
    }

    public function test_api_defaults_theme_to_default_when_not_specified(): void
    {
        $response = $this->postJson('/api/renders', [
            'reciter' => 'yasser-al-dosari',
            'surah' => 108,
            'scope' => 'single',
            'ayah' => 1,
            'layout' => 'reels',
        ]);

        $response->assertStatus(200);
        $uuid = $response->json('uuid');

        $this->assertDatabaseHas('render_jobs', [
            'uuid' => $uuid,
            'theme' => 'default',
        ]);
    }

    public function test_reel_strategy_sets_ayah_and_translation_to_4d3126_and_tafsir_to_white(): void
    {
        $strategy = new ReelSingleLineLayoutStrategy();
        $segment = new Segment(1, 1, 1, [1], 0, 1000, []);

        $layout = $strategy->layout($segment, ['theme' => 'quran_me']);

        $this->assertEquals('quran_me', $layout['theme']);
        $this->assertEquals([77, 49, 38], $layout['ayahColor'], 'Ayah color must be #4D3126 (77, 49, 38)');
        $this->assertEquals([77, 49, 38], $layout['translationBounds']['color'], 'Translation color must be #4D3126 (77, 49, 38)');
        $this->assertEquals([255, 255, 255], $layout['tafsirBounds']['color'], 'Tafsir color must remain pure white #FFFFFF');
    }

    public function test_youtube_strategy_sets_ayah_and_translation_to_4d3126_and_tafsir_to_white(): void
    {
        $strategy = new YouTubeMushafLayoutStrategy();
        $segment = new Segment(1, 1, 1, [1], 0, 1000, []);

        $layout = $strategy->layout($segment, ['theme' => 'quran_me']);

        $this->assertEquals('quran_me', $layout['theme']);
        $this->assertEquals([77, 49, 38], $layout['ayahColor'], 'Ayah color must be #4D3126 (77, 49, 38)');
        $this->assertEquals([77, 49, 38], $layout['translationBounds']['color'], 'Translation color must be #4D3126 (77, 49, 38)');
        $this->assertEquals([255, 255, 255], $layout['tafsirBounds']['color'], 'Tafsir color must remain pure white #FFFFFF');
    }
}
