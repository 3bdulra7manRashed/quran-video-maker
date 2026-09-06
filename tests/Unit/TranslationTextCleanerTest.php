<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\Rendering\Services\TranslationTextCleaner;

class TranslationTextCleanerTest extends TestCase
{
    /**
     * Test 1: Translation without brackets
     */
    public function test_translation_without_brackets(): void
    {
        $input = "Indeed, We have given you al-Kawthar.";
        $expected = "Indeed, We have given you al-Kawthar.";
        $this->assertEquals($expected, TranslationTextCleaner::clean($input));
    }

    /**
     * Test 2: Translation with one bracketed segment
     */
    public function test_translation_with_one_bracketed_segment(): void
    {
        $input = "The moon has split [in two].";
        $expected = "The moon has split.";
        $this->assertEquals($expected, TranslationTextCleaner::clean($input));
    }

    /**
     * Test 3: Translation with multiple bracketed segments
     */
    public function test_translation_with_multiple_bracketed_segments(): void
    {
        $input = "This is a [test] with [multiple] brackets.";
        $expected = "This is a with brackets.";
        $this->assertEquals($expected, TranslationTextCleaner::clean($input));
    }

    /**
     * Test 4: Brackets at beginning of sentence
     */
    public function test_brackets_at_beginning_of_sentence(): void
    {
        $input = "[Indeed] We have given you.";
        $expected = "We have given you.";
        $this->assertEquals($expected, TranslationTextCleaner::clean($input));
    }

    /**
     * Test 5: Brackets at end of sentence
     */
    public function test_brackets_at_end_of_sentence(): void
    {
        $input = "The moon has split [in two]";
        $expected = "The moon has split";
        $this->assertEquals($expected, TranslationTextCleaner::clean($input));
    }

    /**
     * Test 6: Consecutive spaces after removal
     */
    public function test_consecutive_spaces_after_removal(): void
    {
        $input = "word1   [remove]   word2";
        $expected = "word1 word2";
        $this->assertEquals($expected, TranslationTextCleaner::clean($input));
    }

    /**
     * Test 7: Punctuation preservation
     */
    public function test_punctuation_preservation(): void
    {
        $input = "appointment [for due punishment], and";
        $expected = "appointment, and";
        $this->assertEquals($expected, TranslationTextCleaner::clean($input));

        $input2 = "they will turn away and say, \"Passing magic.\" [i.e., miracle]";
        $expected2 = "they will turn away and say, \"Passing magic.\"";
        $this->assertEquals($expected2, TranslationTextCleaner::clean($input2));
    }

    /**
     * Test 8: Deterministic repeated execution
     */
    public function test_deterministic_repeated_execution(): void
    {
        $input = "The Hour is their appointment [for due punishment], and the Hour is more disastrous.";
        $firstClean = TranslationTextCleaner::clean($input);
        $secondClean = TranslationTextCleaner::clean($firstClean);
        $this->assertEquals($firstClean, $secondClean);
    }

    public function test_collapsing_duplicate_commas(): void
    {
        $input = "Indeed, We have granted you, [O Muhammad], al-Kawthar.";
        $expected = "Indeed, We have granted you, al-Kawthar.";
        $this->assertEquals($expected, TranslationTextCleaner::clean($input));
    }

    public function test_strips_footnote_tags_and_html(): void
    {
        $input = 'By the morning brightness<sup foot_note=197778>1</sup> And [by] the night when it covers with darkness,<sup foot_note=197779>2</sup>';
        $cleaned = TranslationTextCleaner::clean($input);
        $this->assertStringNotContainsString('foot_note', $cleaned);
        $this->assertStringNotContainsString('<sup', $cleaned);
        $this->assertEquals('By the morning brightness And the night when it covers with darkness,', $cleaned);
    }
}

