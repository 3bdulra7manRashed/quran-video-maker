<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ContentGeneration\GeneratedContentWordAligner;
use Illuminate\Support\Collection;

class GeneratedContentWordAlignerTest extends TestCase
{
    private GeneratedContentWordAligner $aligner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aligner = new GeneratedContentWordAligner();
    }

    /**
     * Helper to create a dummy Word object.
     */
    private function createWord(int $id, string $uthmaniText)
    {
        $word = new \stdClass();
        $word->id = $id;
        $word->uthmani_text = $uthmaniText;
        return $word;
    }

    /**
     * Helper to create a dummy approved segment object.
     */
    private function createSegment(int $order, string $arabic)
    {
        $seg = new \stdClass();
        $seg->segment_order = $order;
        $seg->arabic = $arabic;
        return $seg;
    }

    /**
     * Test normal matching under standard conditions.
     */
    public function test_align_with_exact_text(): void
    {
        $segments = collect([
            $this->createSegment(1, "إِنَّا أَعْطَيْنَاكَ"),
            $this->createSegment(2, "الْكَوْثَرَ"),
        ]);

        $words = [
            $this->createWord(1, "إِنَّا"),
            $this->createWord(2, "أَعْطَيْنَاكَ"),
            $this->createWord(3, "الْكَوْثَرَ"),
        ];

        $ranges = $this->aligner->align($segments, $words);

        $this->assertCount(2, $ranges);

        // Segment 1 matches words 1 and 2
        $this->assertEquals(1, $ranges[0]['segmentOrder']);
        $this->assertEquals(1, $ranges[0]['startWordId']);
        $this->assertEquals(2, $ranges[0]['endWordId']);
        $this->assertEquals([1, 2], $ranges[0]['wordIds']);

        // Segment 2 matches word 3
        $this->assertEquals(2, $ranges[1]['segmentOrder']);
        $this->assertEquals(3, $ranges[1]['startWordId']);
        $this->assertEquals(3, $ranges[1]['endWordId']);
        $this->assertEquals([3], $ranges[1]['wordIds']);
    }

    /**
     * Test alignment successfully normalizes ayah numbers and diacritics.
     */
    public function test_align_with_diacritics_and_ayah_numbers(): void
    {
        $segments = collect([
            // Has standard alifs, missing diacritics, and ayah number ornament
            $this->createSegment(1, "وازدجر ٩"),
            $this->createSegment(2, "ففتحنا ابواب السماء"),
        ]);

        $words = [
            // Has alif waslah and full diacritics
            $this->createWord(10, "وَٱزْدُجِرَ"),
            $this->createWord(11, "فَفَتَحْنَآ"),
            $this->createWord(12, "أَبْوَابَ"),
            $this->createWord(13, "ٱلسَّمَآءِ"),
        ];

        $ranges = $this->aligner->align($segments, $words);

        $this->assertCount(2, $ranges);

        // Segment 1 matches word 10
        $this->assertEquals(1, $ranges[0]['segmentOrder']);
        $this->assertEquals(10, $ranges[0]['startWordId']);
        $this->assertEquals(10, $ranges[0]['endWordId']);

        // Segment 2 matches words 11, 12, 13
        $this->assertEquals(2, $ranges[1]['segmentOrder']);
        $this->assertEquals(11, $ranges[1]['startWordId']);
        $this->assertEquals(13, $ranges[1]['endWordId']);
        $this->assertEquals([11, 12, 13], $ranges[1]['wordIds']);
    }

    /**
     * Test that a failed match throws AlignmentException.
     */
    public function test_align_failed_match_throws_alignment_exception(): void
    {
        $segments = collect([
            $this->createSegment(1, "إِنَّا أَعْطَيْنَاكَ"),
            $this->createSegment(2, "غَيْرُ مَوْجُودٍ"), // This will fail to match
        ]);

        $words = [
            $this->createWord(1, "إِنَّا"),
            $this->createWord(2, "أَعْطَيْنَاكَ"),
            $this->createWord(3, "الْكَوْثَرَ"),
        ];

        $this->expectException(\App\Services\ContentGeneration\Exceptions\AlignmentException::class);
        $this->aligner->align($segments, $words);
    }

    /**
     * Test that the cursor (startIndex) remains at the start of the failing segment.
     */
    public function test_cursor_remains_unchanged_after_failure(): void
    {
        $segments = collect([
            $this->createSegment(1, "إِنَّا أَعْطَيْنَاكَ"),
            $this->createSegment(2, "مَوْجُودٍ"), // Will fail
        ]);

        $words = [
            $this->createWord(1, "إِنَّا"),
            $this->createWord(2, "أَعْطَيْنَاكَ"),
            $this->createWord(3, "الْكَوْثَرَ"),
        ];

        try {
            $this->aligner->align($segments, $words);
            $this->fail("Expected AlignmentException was not thrown.");
        } catch (\App\Services\ContentGeneration\Exceptions\AlignmentException $e) {
            // The first segment consumed 2 words (indexes 0 and 1).
            // So the second segment started at word index 2.
            $this->assertEquals(2, $e->getStartIndex());
        }
    }

    /**
     * Test 6: Runaway accumulation stops early using SAFE_MARGIN.
     */
    public function test_runaway_accumulation_stops_early(): void
    {
        $segments = collect([
            $this->createSegment(1, "مَوْجُودٍ"), // Segment is normalized to "موجود" (length 5)
        ]);

        $words = [
            $this->createWord(1, "إِنَّا"),         // "انا" (length 3)
            $this->createWord(2, "أَعْطَيْنَاكَ"),  // "اعطيناك" (length 8) -> accumulated: "انااعطيناك" (length 11) > segment length + 3 (5 + 3 = 8)
            $this->createWord(3, "الْكَوْثَرَ"),    // Should NOT be accumulated
            $this->createWord(4, "فَصَلِّ"),       // Should NOT be accumulated
        ];

        try {
            $this->aligner->align($segments, $words);
            $this->fail("Expected AlignmentException was not thrown.");
        } catch (\App\Services\ContentGeneration\Exceptions\AlignmentException $e) {
            // Assert that only Word 1 and Word 2 were matched/accumulated before early exit
            $matchedWords = $e->getMatchedWords();
            $this->assertCount(2, $matchedWords);
            $this->assertEquals(1, $matchedWords[0]->id);
            $this->assertEquals(2, $matchedWords[1]->id);
        }
    }
}
