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

    public function test_reel_strategy_configures_footer_bounds_for_quran_me_theme(): void
    {
        $strategy = new ReelSingleLineLayoutStrategy();
        $segment = new Segment(1, 1, 1, [1], 0, 1000, []);

        $layout = $strategy->layout($segment, ['theme' => 'quran_me']);

        $this->assertFalse($layout['header']['show']);
        $this->assertArrayHasKey('footerBounds', $layout);
        $this->assertEquals(540, $layout['footerBounds']['x']);
        $this->assertEquals([77, 49, 38], $layout['footerBounds']['color']);
        $this->assertEquals(1260, $layout['footerBounds']['surah']['y']);
        $this->assertEquals(-18, $layout['footerBounds']['surah']['opticalOffsetX']);
        $this->assertEquals(1345, $layout['footerBounds']['reciter']['y']);
        $this->assertEquals(1415, $layout['footerBounds']['watermark']['y']);
        $this->assertEquals('equran.me', $layout['footerBounds']['watermark']['text']);
    }

    public function test_footer_layer_resolves_all_fonts_and_surah_glyphs(): void
    {
        $footerLayer = new \App\Modules\Rendering\Layers\FooterLayer();

        $this->assertFileExists($footerLayer->resolveBsmlFont());
        $this->assertFileExists($footerLayer->resolveArabicFont());
        $this->assertFileExists($footerLayer->resolveGeorgiaFont());

        // Surah 1: U+FB8D U+FB8C
        $glyph1 = $footerLayer->resolveSurahGlyph(1);
        $this->assertNotNull($glyph1);
        $this->assertEquals(mb_chr(0xFB8D, 'UTF-8') . mb_chr(0xFB8C, 'UTF-8'), $glyph1);

        // Surah 18 (Al-Kahf): U+FB9E U+FB8C (0xFB8C + 18 = 0xFB9E)
        $glyph18 = $footerLayer->resolveSurahGlyph(18);
        $this->assertNotNull($glyph18);
        $this->assertEquals(mb_chr(0xFB9E, 'UTF-8') . mb_chr(0xFB8C, 'UTF-8'), $glyph18);

        // Surah 93: U+FC0A U+FB8C
        $glyph93 = $footerLayer->resolveSurahGlyph(93);
        $this->assertNotNull($glyph93);
        $this->assertEquals(mb_chr(0xFC0A, 'UTF-8') . mb_chr(0xFB8C, 'UTF-8'), $glyph93);
    }

    public function test_footer_layer_renders_surah_al_kahf_with_optical_centering_offset(): void
    {
        $footerLayer = new \App\Modules\Rendering\Layers\FooterLayer();
        $im = imagecreatetruecolor(1080, 1920);

        $segment = new Segment(1, 18, 1, [1], 0, 1000, []);
        $layoutData = [
            'theme' => 'quran_me',
            'surahNumber' => 18, // Surah Al-Kahf
            'reciterNameArabic' => 'مشاري راشد العفاسي',
            'footerBounds' => [
                'show' => true,
                'x' => 540,
                'color' => [77, 49, 38],
                'surah' => [
                    'y' => 1260,
                    'fontSize' => 45,
                    'fontPath' => $footerLayer->resolveBsmlFont(),
                    'opticalOffsetX' => -18,
                ],
                'reciter' => [
                    'y' => 1345,
                    'fontSize' => 28,
                    'fontPath' => $footerLayer->resolveArabicFont(),
                ],
                'watermark' => [
                    'text' => 'equran.me',
                    'y' => 1415,
                    'fontSize' => 24,
                    'fontPath' => $footerLayer->resolveGeorgiaFont(),
                ],
            ],
        ];

        $context = new \App\Modules\Rendering\Domain\FrameContext($im, 1080, 1920, $layoutData, $segment);
        $footerLayer->render($context);

        $this->assertNotNull($im);
        imagedestroy($im);
    }
}
