<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Modules\Rendering\Services\TranslationChunker;

class TranslationChunkerTest extends TestCase
{
    public function test_single_complete_ayah(): void
    {
        $arabic = "إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ";
        $english = "Indeed, We have granted you, [O Muhammad], al-Kawthar.";
        
        // When the range covers the full Arabic text (0 to 2 out of 3 words)
        $chunk = TranslationChunker::chunk($arabic, $english, 0, 2, 3);
        
        // Should return the full translation unchanged
        $this->assertEquals("Indeed, We have granted you, [O Muhammad], al-Kawthar.", $chunk);
    }

    public function test_ayah_split_into_2_segments(): void
    {
        $arabic = "إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ";
        $english = "Indeed, We have granted you, [O Muhammad], al-Kawthar."; // 8 words
        
        // Segment 1: first 2 words (indices 0 to 1 of 3)
        // englishStartIndex = floor(0/3 * 8) = 0
        // englishEndIndex = ceil(2/3 * 8) = 6
        $chunk1 = TranslationChunker::chunk($arabic, $english, 0, 1, 3);
        $this->assertEquals("Indeed, We have granted you, [O", $chunk1);

        // Segment 2: last word (index 2 to 2 of 3)
        // englishStartIndex = floor(2/3 * 8) = 5
        // englishEndIndex = ceil(3/3 * 8) = 8
        $chunk2 = TranslationChunker::chunk($arabic, $english, 2, 2, 3);
        $this->assertEquals("[O Muhammad], al-Kawthar.", $chunk2);
    }

    public function test_ayah_split_into_3_segments(): void
    {
        $arabic = "اقتربت الساعة وانشق القمر"; // 4 words
        $english = "The Hour has come near, and the moon has split"; // 10 words

        // Segment 1: word 0
        // start=0, end=0. floor(0) = 0, ceil(1/4*10) = 3
        $chunk1 = TranslationChunker::chunk($arabic, $english, 0, 0, 4);
        $this->assertEquals("The Hour has", $chunk1);

        // Segment 2: words 1 to 2
        // start=1, end=2. floor(1/4*10) = 2, ceil(3/4*10) = 8
        $chunk2 = TranslationChunker::chunk($arabic, $english, 1, 2, 4);
        $this->assertEquals("has come near, and the moon", $chunk2);

        // Segment 3: word 3
        // start=3, end=3. floor(3/4*10) = 7, ceil(4/4*10) = 10
        $chunk3 = TranslationChunker::chunk($arabic, $english, 3, 3, 4);
        $this->assertEquals("moon has split", $chunk3);
    }

    public function test_translation_shorter_than_arabic(): void
    {
        $arabic = "word1 word2 word3 word4 word5"; // 5 words
        $english = "short translation"; // 2 words

        // Segment: first half (0 to 1 of 5)
        // start=0, end=1. floor(0) = 0, ceil(2/5 * 2) = ceil(0.8) = 1
        $chunk1 = TranslationChunker::chunk($arabic, $english, 0, 1, 5);
        $this->assertEquals("short", $chunk1);

        // Segment: second half (2 to 4 of 5)
        // start=2, end=4. floor(2/5*2) = 0, ceil(5/5*2) = 2
        $chunk2 = TranslationChunker::chunk($arabic, $english, 2, 4, 5);
        $this->assertEquals("short translation", $chunk2);
    }

    public function test_translation_longer_than_arabic(): void
    {
        $arabic = "w1 w2"; // 2 words
        $english = "this is a very long english translation sentence"; // 8 words

        // Segment 1: w1 (0 to 0)
        // start=0, end=0. floor(0) = 0, ceil(1/2*8) = 4
        $chunk1 = TranslationChunker::chunk($arabic, $english, 0, 0, 2);
        $this->assertEquals("this is a very", $chunk1);

        // Segment 2: w2 (1 to 1)
        // start=1, end=1. floor(1/2*8) = 4, ceil(2/2*8) = 8
        $chunk2 = TranslationChunker::chunk($arabic, $english, 1, 1, 2);
        $this->assertEquals("long english translation sentence", $chunk2);
    }

    public function test_one_word_segments(): void
    {
        $arabic = "w1 w2 w3";
        $english = "one two three";

        $chunk = TranslationChunker::chunk($arabic, $english, 1, 1, 3);
        $this->assertEquals("two", $chunk);
    }

    public function test_boundary_conditions(): void
    {
        $arabic = "w1";
        $english = "";
        
        $chunk = TranslationChunker::chunk($arabic, $english, 0, 0, 1);
        $this->assertEquals("", $chunk);

        // Non-positive total Arabic words
        $chunk2 = TranslationChunker::chunk("w1", "hello world", 0, 0, 0);
        $this->assertEquals("hello world", $chunk2);
    }

    public function test_punctuation_preservation(): void
    {
        $arabic = "w1 w2";
        $english = "Indeed, yes.";

        $chunk1 = TranslationChunker::chunk($arabic, $english, 0, 0, 2);
        $this->assertEquals("Indeed,", $chunk1);

        $chunk2 = TranslationChunker::chunk($arabic, $english, 1, 1, 2);
        $this->assertEquals("yes.", $chunk2);
    }

    public function test_deterministic_repeated_execution(): void
    {
        $arabic = "w1 w2 w3";
        $english = "deterministic text here";

        $chunkA = TranslationChunker::chunk($arabic, $english, 1, 2, 3);
        $chunkB = TranslationChunker::chunk($arabic, $english, 1, 2, 3);

        $this->assertEquals($chunkA, $chunkB);
    }

    public function test_equal_arabic_english_word_counts(): void
    {
        $arabic = "w1 w2 w3";
        $english = "first second third";

        $chunk = TranslationChunker::chunk($arabic, $english, 1, 2, 3);
        $this->assertEquals("second third", $chunk);
    }

    public function test_index_clamping(): void
    {
        $arabic = "w1 w2";
        $english = "hello world";

        // Start index too high, end index too high
        $chunk = TranslationChunker::chunk($arabic, $english, 5, 10, 2);
        $this->assertEquals("world", $chunk);

        // Start index negative, end index negative
        $chunk2 = TranslationChunker::chunk($arabic, $english, -5, -2, 2);
        $this->assertEquals("hello", $chunk2);
    }

    public function test_non_contiguous_word_indexes(): void
    {
        $arabic = "w1 w2 w3 w4 w5";
        $english = "one two three four five";

        // Segment has words 1 and 4 (positions 1 and 4)
        $chunk = TranslationChunker::chunk($arabic, $english, 1, 4, 5);
        $this->assertEquals("two three four five", $chunk);
    }
}
