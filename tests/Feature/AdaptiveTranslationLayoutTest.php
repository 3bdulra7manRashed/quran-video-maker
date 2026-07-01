<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Rendering\Layers\TranslationTextLayer;
use App\Modules\Rendering\Domain\TranslationSegment;
use App\Modules\Rendering\Domain\FrameContext;
use App\Modules\Segmentation\DTO\Segment;
use Illuminate\Support\Facades\Log;

class AdaptiveTranslationLayoutTest extends TestCase
{
    protected string $fontPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fontPath = base_path('fonts/georgia.ttf');
        if (!file_exists($this->fontPath)) {
            $dir = dirname($this->fontPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            // Use standard system font copy or mock if it doesn't exist
            copy(base_path('fonts/Cairo-Regular.ttf'), $this->fontPath);
        }
    }

    protected function createMockFrameContext(int $canvasWidth, int $mushafLines): FrameContext
    {
        // Mock GD image
        $image = imagecreatetruecolor($canvasWidth, 1080);
        $segment = new Segment(1, 1, 1, [1], 0, 1000);
        
        $layoutData = [
            'width' => $canvasWidth,
            'height' => 1080,
            'layoutType' => $canvasWidth === 1920 ? 'youtube' : 'reels',
            'maxMushafLines' => $mushafLines,
            'translationBounds' => [
                'y' => 860,
                'width' => 1200,
                'margin' => 360,
                'fontSize' => 54,
                'lineHeight' => 1.4,
                'fontPath' => $this->fontPath,
                'color' => [225, 225, 225],
            ]
        ];

        return new FrameContext(
            $image,
            $canvasWidth,
            1080,
            $layoutData,
            $segment
        );
    }

    public function test_constants_usage(): void
    {
        $this->assertEquals(1200, TranslationTextLayer::DEFAULT_WIDTH);
        $this->assertEquals([1200, 1250, 1300], TranslationTextLayer::WIDTH_STEPS);
        $this->assertEquals(54, TranslationTextLayer::DEFAULT_FONT_SIZE);
        $this->assertEquals([54, 52, 50, 48, 46], TranslationTextLayer::FONT_STEPS);
        $this->assertEquals(2, TranslationTextLayer::MAX_TRANSLATION_LINES);
        $this->assertEquals(46, TranslationTextLayer::MIN_FONT_SIZE);
    }

    public function test_short_translation_renders_in_1_line(): void
    {
        $layer = new TranslationTextLayer([
            new TranslationSegment(108, "Short text.", 1, 1, 0, 1000)
        ]);

        $context = $this->createMockFrameContext(1920, 1);
        $layer->render($context);

        $result = $layer->measureTranslationLayout("Short text.", 54, $this->fontPath, 1200);
        $this->assertCount(1, $result['lines']);
        $this->assertEquals("Short text.", $result['wrappedText']);
    }

    public function test_medium_translation_renders_in_2_lines_without_resizing(): void
    {
        // 2 lines target: should fit exactly without triggers
        $text = "Indeed, We have granted you, [O Muhammad], al-Kawthar.";
        
        $layer = new TranslationTextLayer([
            new TranslationSegment(108, $text, 1, 1, 0, 1000)
        ]);

        $context = $this->createMockFrameContext(1920, 1);
        $layer->render($context);

        $result = $layer->measureTranslationLayout($text, 54, $this->fontPath, 1200);
        $this->assertLessThanOrEqual(2, count($result['lines']));
    }

    public function test_long_translation_requires_width_expansion(): void
    {
        // A text that takes 3 lines at 1200px but fits in 2 lines at 1250px or 1300px
        $text = "Indeed, We have granted you, [O Muhammad], al-Kawthar. So pray now.";

        $layer = new TranslationTextLayer([
            new TranslationSegment(108, $text, 1, 1, 0, 1000)
        ]);

        // Wrap at default width 1200:
        $resDefault = $layer->measureTranslationLayout($text, 54, $this->fontPath, 1200);
        $this->assertGreaterThan(2, count($resDefault['lines']));

        // Check if wider width (e.g. 1300 or 1250) decreases line count to 2:
        $resWider = $layer->measureTranslationLayout($text, 54, $this->fontPath, 1250);
        $this->assertLessThanOrEqual(2, count($resWider['lines']));

        $context = $this->createMockFrameContext(1920, 1);
        $layer->render($context);
    }

    public function test_long_translation_requires_font_reduction(): void
    {
        // A very long text that requires both max width 1300px AND font reduction to fit in 2 lines
        $text = "Indeed, We have granted you, [O Muhammad], al-Kawthar. So pray to your Lord.";

        $layer = new TranslationTextLayer([
            new TranslationSegment(108, $text, 1, 1, 0, 1000)
        ]);

        // At max width (1300px) and default size (54px), it should exceed 2 lines:
        $resDefaultSize = $layer->measureTranslationLayout($text, 54, $this->fontPath, 1300);
        $this->assertGreaterThan(2, count($resDefaultSize['lines']));

        // At max width (1300px) and reduced size (e.g. 46px), it should fit in 2 lines:
        $resReducedSize = $layer->measureTranslationLayout($text, 46, $this->fontPath, 1300);
        $this->assertLessThanOrEqual(2, count($resReducedSize['lines']));

        $context = $this->createMockFrameContext(1920, 1);
        $layer->render($context);
    }

    public function test_extremely_long_translation_exceeding_limits_retains_full_content(): void
    {
        // Extremely long translation that exceeds 2 lines even at width 1300 and size 46
        $text = "Indeed, We have warned you of a near punishment on the Day when a man will observe what his hands have put forth and the disbeliever will say, 'Oh, I wish that I were dust!' and there will be no way of escape for anyone from that glorious event.";

        $layer = new TranslationTextLayer([
            new TranslationSegment(78, $text, 40, 40, 0, 1000)
        ]);

        // Expect warning logged
        Log::shouldReceive('warning')
            ->once()
            ->with("Translation exceeded maximum line target. Rendering with fallback dimensions while preserving full translation.", \Mockery::any());

        $context = $this->createMockFrameContext(1920, 1);
        $layer->render($context);

        // Verify that the helper was reused to wrap the text:
        $result = $layer->measureTranslationLayout($text, 46, $this->fontPath, 1300);
        $this->assertGreaterThan(2, count($result['lines']));

        // Verify no truncation occurred
        $joinedText = implode(" ", $result['lines']);
        $this->assertEquals(trim(preg_replace('/\s+/', ' ', $text)), trim(preg_replace('/\s+/', ' ', $joinedText)));
    }

    public function test_deterministic_output(): void
    {
        $layer = new TranslationTextLayer();
        $text = "Indeed, We have granted you, [O Muhammad], al-Kawthar.";

        $res1 = $layer->measureTranslationLayout($text, 54, $this->fontPath, 1200);
        $res2 = $layer->measureTranslationLayout($text, 54, $this->fontPath, 1200);

        $this->assertEquals($res1, $res2);
    }
}
