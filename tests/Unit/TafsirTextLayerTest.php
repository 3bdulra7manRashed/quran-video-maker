<?php

namespace Tests\Unit;

use App\Modules\Rendering\Domain\FrameContext;
use App\Modules\Rendering\Domain\TranslationSegment;
use App\Modules\Rendering\Layers\TafsirTextLayer;
use App\Modules\Segmentation\DTO\Segment;
use Tests\TestCase;

class TafsirTextLayerTest extends TestCase
{
    protected string $fontPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fontPath = base_path('fonts/Al-Jazeera-Arabic-Regular.ttf');
        if (!file_exists($this->fontPath)) {
            $this->fontPath = base_path('fonts/Cairo-Regular.ttf');
        }
    }

    public function test_resolves_al_jazeera_font_with_fallbacks(): void
    {
        $layer = new TafsirTextLayer();
        $resolved = $layer->resolveFontPath();

        $this->assertFileExists($resolved);
        $this->assertTrue(
            str_contains($resolved, 'Al-Jazeera-Arabic') ||
            str_contains($resolved, 'Amiri') ||
            str_contains($resolved, 'Cairo')
        );
    }

    public function test_wraps_arabic_text_without_exceeding_max_width(): void
    {
        $layer = new TafsirTextLayer();
        $text = "الثناء على الله بصفاته التامة ونعمه العظيمة الظاهرة والباطنة الدينية والدنيوية";

        $layout = $layer->measureTafsirLayout($text, 30, $this->fontPath, 920, 44);

        $this->assertNotEmpty($layout['lines']);
        $this->assertLessThanOrEqual(2, count($layout['lines']));

        foreach ($layout['lines'] as $line) {
            // Ensure no embedded newlines
            $this->assertStringNotContainsString("\n", $line);

            $bbox = imagettfbbox(30, 0, $this->fontPath, $line);
            $width = abs($bbox[4] - $bbox[0]);
            $this->assertLessThanOrEqual(920, $width);
        }
    }

    public function test_renders_1_line_text_centered_at_target_y(): void
    {
        $layer = new TafsirTextLayer();

        $width = 1080;
        $height = 1920;
        $im = imagecreatetruecolor($width, $height);
        // Fill canvas with black
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 0, 0, 0));

        $arabicSegment = new Segment(1, 1, 5, [1, 2, 3, 4, 5], 0, 1000);
        $tafsirSegment = new TranslationSegment(1, "الحمد لله رب العالمين", 1, 1, 0, 1000);

        $layoutData = [
            'tafsirBounds' => [
                'x' => 540,
                'y' => 1081,
                'width' => 920,
                'fontSize' => 30,
                'lineHeight' => 44,
                'fontPath' => $this->fontPath,
                'color' => [255, 255, 255],
            ],
        ];

        $context = new FrameContext($im, $width, $height, $layoutData, $arabicSegment);
        $layer->render($tafsirSegment, $context);

        // Find white pixels drawn on the canvas
        $whitePixelsY = [];
        $whitePixelsX = [];

        for ($y = 1000; $y <= 1160; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r > 200 && $g > 200 && $b > 200) {
                    $whitePixelsY[] = $y;
                    $whitePixelsX[] = $x;
                }
            }
        }

        imagedestroy($im);

        $this->assertNotEmpty($whitePixelsY, "White text pixels should be rendered");

        $minY = min($whitePixelsY);
        $maxY = max($whitePixelsY);
        $centerY = ($minY + $maxY) / 2.0;

        $minX = min($whitePixelsX);
        $maxX = max($whitePixelsX);
        $centerX = ($minX + $maxX) / 2.0;

        // Verify text vertical center is aligned with Y = 1081 (within 2px tolerance)
        $this->assertEqualsWithDelta(1081.0, $centerY, 2.0, "1-line text must center vertically at Y = 1081");

        // Verify horizontal center is aligned with X = 540 (within 2px tolerance)
        $this->assertEqualsWithDelta(540.0, $centerX, 3.0, "1-line text must center horizontally at X = 540");
    }

    public function test_renders_2_line_text_balanced_around_target_y(): void
    {
        $layer = new TafsirTextLayer();

        $width = 1080;
        $height = 1920;
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 0, 0, 0));

        $arabicSegment = new Segment(1, 1, 5, [1, 2, 3, 4, 5], 0, 1000);
        $mediumText = "الثناء على الله بصفاته التامة ونعمه العظيمة الظاهرة والباطنة الدينية والدنيوية";
        $tafsirSegment = new TranslationSegment(1, $mediumText, 1, 1, 0, 1000);

        $layoutData = [
            'tafsirBounds' => [
                'x' => 540,
                'y' => 1081,
                'width' => 920,
                'fontSize' => 30,
                'lineHeight' => 44,
                'fontPath' => $this->fontPath,
                'color' => [255, 255, 255],
            ],
        ];

        $context = new FrameContext($im, $width, $height, $layoutData, $arabicSegment);
        $layer->render($tafsirSegment, $context);

        // Find white pixels drawn on the canvas
        $whitePixelsY = [];
        $whitePixelsX = [];

        for ($y = 1000; $y <= 1160; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r > 200 && $g > 200 && $b > 200) {
                    $whitePixelsY[] = $y;
                    $whitePixelsX[] = $x;
                }
            }
        }

        imagedestroy($im);

        $this->assertNotEmpty($whitePixelsY, "White text pixels should be rendered");

        $minY = min($whitePixelsY);
        $maxY = max($whitePixelsY);
        $centerY = ($minY + $maxY) / 2.0;

        $minX = min($whitePixelsX);
        $maxX = max($whitePixelsX);
        $centerX = ($minX + $maxX) / 2.0;

        // Verify text vertical center is balanced around Y = 1081 (within 3px tolerance)
        $this->assertEqualsWithDelta(1081.0, $centerY, 3.0, "2-line text must balance vertically around Y = 1081");

        // Verify horizontal center is aligned with X = 540 (within 3px tolerance)
        $this->assertEqualsWithDelta(540.0, $centerX, 3.0, "2-line text must center horizontally at X = 540");
    }

    public function test_prioritizes_custom_segment_tafsir_over_bundled_ayah_tafsir(): void
    {
        $layer = new TafsirTextLayer();

        $segment = new Segment(1, 1, 1, [1], 0, 1000);
        $segment->tafsir = "أقسم الله بوقت الضحى";

        $bundledAyahTafsir = new TranslationSegment(1, "تفسير الآيات 1 و 2 و 3 مجمعة معاً", 1, 1, 0, 1000);

        $context = new FrameContext(
            imagecreatetruecolor(10, 10),
            1080,
            1920,
            ['tafsir' => 'context tafsir'],
            $segment
        );

        $resolved = $layer->resolveTafsirText($segment, $context, $bundledAyahTafsir);

        $this->assertEquals("أقسم الله بوقت الضحى", $resolved);
    }

    public function test_resolves_tafsir_from_array_or_context_fallback(): void
    {
        $layer = new TafsirTextLayer();

        // 1. Test from array
        $arraySeg = ['tafsir' => "وبالليل إذا سكن"];
        $this->assertEquals("وبالليل إذا سكن", $layer->resolveTafsirText($arraySeg));

        // 2. Test from context layoutData
        $segmentWithoutTafsir = new Segment(1, 1, 1, [1], 0, 1000);
        $context = new FrameContext(
            imagecreatetruecolor(10, 10),
            1080,
            1920,
            ['tafsir' => "تفسير من سياق الإطار"],
            $segmentWithoutTafsir
        );
        $this->assertEquals("تفسير من سياق الإطار", $layer->resolveTafsirText(null, $context, null));

        // 3. Test fallback to active TranslationSegment text
        $fallback = new TranslationSegment(1, "تفسير احتياطي", 1, 1, 0, 1000);
        $this->assertEquals("تفسير احتياطي", $layer->resolveTafsirText(null, null, $fallback));
    }

    public function test_cleans_footnote_tags_and_html_from_tafsir(): void
    {
        $layer = new TafsirTextLayer();

        $rawTafsir = '<b>أقسم الله</b> بوقت الضحى<sup foot_note=197778>1</sup> ومقدمات <span class="note">النهار</span>.';
        $segment = new Segment(1, 1, 1, [1], 0, 1000);
        $segment->tafsir = $rawTafsir;

        $resolved = $layer->resolveTafsirText($segment);

        $this->assertEquals('أقسم الله بوقت الضحى ومقدمات النهار.', $resolved);
        $this->assertStringNotContainsString('foot_note', $resolved);
        $this->assertStringNotContainsString('<sup', $resolved);
        $this->assertStringNotContainsString('<b>', $resolved);
    }

    public function test_strip_diacritics_removes_tashkeel_and_tatweel_while_preserving_all_hamzas(): void
    {
        $layer = new TafsirTextLayer();

        // Sample containing fatha, damma, kasra, tanween, shadda, sukun, tatweel, and all hamzas:
        // أ, إ, آ, ء, ئ, ؤ
        $input = "أَلَمْـ إِيمَانٌـ آمَنَـ سَمَاءٌـ بِئْسَـ مُؤْمِنٌـ فَآوَىٰ";
        $cleaned = $layer->stripDiacritics($input);

        // Verify tatweel is removed
        $this->assertStringNotContainsString('ـ', $cleaned);

        // Verify harakat/tashkeel are removed
        $this->assertMatchesRegularExpression('/^[\x{0621}-\x{064A}\x{0671}\s]+$/u', $cleaned);

        // Verify all 6 hamza variants are strictly preserved
        $this->assertStringContainsString('ألم', $cleaned);
        $this->assertStringContainsString('إيمان', $cleaned);
        $this->assertStringContainsString('آمن', $cleaned);
        $this->assertStringContainsString('سماء', $cleaned);
        $this->assertStringContainsString('بئس', $cleaned);
        $this->assertStringContainsString('مؤمن', $cleaned);
        $this->assertStringContainsString('فآوى', $cleaned);
    }

    public function test_surah_ad_duha_ayah_6_tafsir_preserves_hamzas_without_tashkeel(): void
    {
        $layer = new TafsirTextLayer();

        $ayah6Tafsir = "أَلَمْ يَجِدْكَ يَتِيمًا فَآوَىٰ";
        $cleaned = $layer->stripDiacritics($ayah6Tafsir);

        $this->assertEquals("ألم يجدك يتيما فآوى", $cleaned);

        // Verify resolveTafsirText also strips diacritics
        $segment = new Segment(6, 1, 6, [1, 2, 3, 4], 0, 1000);
        $segment->tafsir = $ayah6Tafsir;

        $resolved = $layer->resolveTafsirText($segment);
        $this->assertEquals("ألم يجدك يتيما فآوى", $resolved);
    }

    public function test_dynamic_stacking_positions_tafsir_below_translation_with_clean_margin(): void
    {
        $layer = new TafsirTextLayer();

        $width = 1080;
        $height = 1920;
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 0, 0, 0));

        $arabicSegment = new Segment(1, 1, 5, [1, 2, 3, 4, 5], 0, 1000);
        $tafsirSegment = new TranslationSegment(1, "أقسم الله بوقت الضحى", 1, 1, 0, 1000);

        $translationBottomY = 1200;
        $layoutData = [
            'translationBottomY' => $translationBottomY,
            'tafsirBounds' => [
                'x' => 540,
                'y' => 1081,
                'width' => 920,
                'fontSize' => 30,
                'lineHeight' => 44,
                'fontPath' => $this->fontPath,
                'color' => [255, 255, 255],
                'dynamicStacking' => true,
                'stackMargin' => 40,
            ],
        ];

        $context = new FrameContext($im, $width, $height, $layoutData, $arabicSegment);
        $layer->render($tafsirSegment, $context);

        $whitePixelsY = [];
        $whitePixelsX = [];
        for ($y = 1200; $y <= 1350; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r > 200 && $g > 200 && $b > 200) {
                    $whitePixelsY[] = $y;
                    $whitePixelsX[] = $x;
                }
            }
        }

        imagedestroy($im);

        $this->assertNotEmpty($whitePixelsY, "Tafsir white pixels must be drawn in the lower region below translation");
        $minY = min($whitePixelsY);
        $minX = min($whitePixelsX);
        $maxX = max($whitePixelsX);
        $centerX = ($minX + $maxX) / 2.0;

        // Verify Tafsir starts around translationBottomY + 40 (1240), with small glyph tolerance
        $this->assertEqualsWithDelta(1240.0, (float) $minY, 3.0, "Tafsir should start at translationBottomY + 40");

        // Verify horizontal center is aligned with X = 540
        $this->assertEqualsWithDelta(540.0, $centerX, 3.0, "Tafsir must remain horizontally centered at X = 540");
    }

    public function test_renders_dynamic_pill_capsule_for_quran_me_theme(): void
    {
        $layer = new TafsirTextLayer();

        $width = 1080;
        $height = 1920;
        $im = imagecreatetruecolor($width, $height);
        // Fill canvas with black
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 0, 0, 0));

        $arabicSegment = new Segment(1, 1, 5, [1, 2, 3, 4, 5], 0, 1000);
        $tafsirSegment = new TranslationSegment(1, "أقسم الله بأول النهار حين ترتفع الشمس", 1, 1, 0, 1000);

        $layoutData = [
            'theme' => 'quran_me',
            'tafsirBounds' => [
                'x' => 540,
                'y' => 1081,
                'width' => 920,
                'fontSize' => 30,
                'lineHeight' => 44,
                'fontPath' => $this->fontPath,
                'color' => [255, 255, 255],
            ],
        ];

        $context = new FrameContext($im, $width, $height, $layoutData, $arabicSegment);
        $layer->render($tafsirSegment, $context);

        // Check context recorded capsule bounds
        $this->assertArrayHasKey('tafsirCapsuleBounds', $context->layoutData);
        $capsuleBounds = $context->layoutData['tafsirCapsuleBounds'];
        $expectedRadius = floor($capsuleBounds['height'] / 2.0);
        $this->assertEquals($expectedRadius, $capsuleBounds['radius'], "Radius must equal half the capsule height (full pill)");
        $this->assertEquals([77, 49, 38], $capsuleBounds['color'], "Capsule color must be #4D3126");
        $this->assertEquals(51, $capsuleBounds['alpha'], "Capsule alpha must be 51 for 60% opacity");
        $this->assertEquals(0.60, $capsuleBounds['opacity']);
        $this->assertLessThanOrEqual(940.0, $capsuleBounds['width']);
        $this->assertEqualsWithDelta(540.0, ($capsuleBounds['x1'] + $capsuleBounds['x2']) / 2.0, 1.0);
        $this->assertEqualsWithDelta(1081.0, ($capsuleBounds['y1'] + $capsuleBounds['y2']) / 2.0, 1.0);

        // Check for capsule color pixels (#4D3126 at 60% opacity over black: R~46, G~29, B~23)
        $capsulePixelsX = [];
        $capsulePixelsY = [];
        for ($y = 1030; $y <= 1130; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r >= 44 && $r <= 48 && $g >= 27 && $g <= 31 && $b >= 21 && $b <= 25) {
                    $capsulePixelsX[] = $x;
                    $capsulePixelsY[] = $y;
                }
            }
        }

        $this->assertNotEmpty($capsulePixelsX, "Capsule pixels (#4D3126 at 60% opacity) should be rendered");
        $centerX = (min($capsulePixelsX) + max($capsulePixelsX)) / 2.0;
        $centerY = (min($capsulePixelsY) + max($capsulePixelsY)) / 2.0;

        $this->assertEqualsWithDelta(540.0, $centerX, 2.0, "Capsule must center horizontally at X = 540");
        $this->assertEqualsWithDelta(1081.0, $centerY, 2.0, "Capsule must center vertically around Y = 1081");

        imagedestroy($im);
    }
}



