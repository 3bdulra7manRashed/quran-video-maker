<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ContentGeneration\AiJsonRepairService;

class AiJsonRepairServiceTest extends TestCase
{
    private AiJsonRepairService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiJsonRepairService();
    }

    /**
     * Test 1: Valid JSON is unchanged
     */
    public function test_valid_json_is_unchanged(): void
    {
        $json = '{
  "segments": [
    {
      "order": 1,
      "arabic": "إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ",
      "translation": "Indeed, We have granted you, [O Muhammad], al-Kawthar.",
      "tafsir": "Indeed, We have granted you al-Kawthar."
    }
  ]
}';
        $repaired = $this->service->repair($json);
        $this->assertEquals($json, $repaired);

        // Verify it parses successfully
        $decoded = json_decode($repaired, true);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertEquals("Indeed, We have granted you, [O Muhammad], al-Kawthar.", $decoded['segments'][0]['translation']);
    }

    /**
     * Test 2: Unescaped inner quotes are repaired
     */
    public function test_unescaped_inner_quotes_repaired(): void
    {
        $json = '{
  "segments": [
    {
      "order": 1,
      "arabic": "اقتربت الساعة",
      "translation": "and said, "A madman,"",
      "tafsir": "meaning, "They said he is crazy""
    }
  ]
}';
        $repaired = $this->service->repair($json);

        // Verify it now parses successfully
        $decoded = json_decode($repaired, true);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertEquals('and said, "A madman,"', $decoded['segments'][0]['translation']);
        $this->assertEquals('meaning, "They said he is crazy"', $decoded['segments'][0]['tafsir']);
    }

    /**
     * Test 3: Double outer quotes are repaired
     */
    public function test_double_outer_quotes_repaired(): void
    {
        $json = '{
  "segments": [
    {
      "order": 1,
      "arabic": "إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ",
      "translation": ""Indeed, I am overpowered, so help."",
      "tafsir": ""Help me against them.""
    }
  ]
}';
        $repaired = $this->service->repair($json);

        // Verify it now parses successfully
        $decoded = json_decode($repaired, true);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertEquals('"Indeed, I am overpowered, so help."', $decoded['segments'][0]['translation']);
        $this->assertEquals('"Help me against them."', $decoded['segments'][0]['tafsir']);
    }

    /**
     * Test 4: Multiple repaired fields are repaired
     */
    public function test_multiple_repaired_fields(): void
    {
        $json = '{
  "segments": [
    {
      "order": 1,
      "arabic": "إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ",
      "translation": ""Indeed, I am overpowered, so help."",
      "tafsir": "meaning, "They said he is crazy""
    }
  ]
}';
        $repaired = $this->service->repair($json);

        // Verify it parses successfully
        $decoded = json_decode($repaired, true);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertEquals('"Indeed, I am overpowered, so help."', $decoded['segments'][0]['translation']);
        $this->assertEquals('meaning, "They said he is crazy"', $decoded['segments'][0]['tafsir']);
    }

    /**
     * Test 5: Completely invalid JSON fails
     */
    public function test_completely_invalid_json_fails(): void
    {
        $json = '{{invalid}}';
        $repaired = $this->service->repair($json);

        // Verify it still fails parsing
        json_decode($repaired);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Test 6: Random text fails
     */
    public function test_random_text_fails(): void
    {
        $json = 'Here is the output from Gemini: ok';
        $repaired = $this->service->repair($json);

        // Verify it still fails parsing
        json_decode($repaired);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Test 7: Missing braces fails
     */
    public function test_missing_braces_fails(): void
    {
        $json = '{
  "segments": [
    {
      "order": 1,
      "arabic": "إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ",
      "translation": "Indeed"
';
        $repaired = $this->service->repair($json);

        // Verify it still fails parsing
        json_decode($repaired);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Test 8: Repair preserves original content
     */
    public function test_repair_preserves_original_content(): void
    {
        $json = '{
  "segments": [
    {
      "order": 1,
      "arabic": "إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ",
      "translation": "and said, "A madman,"",
      "tafsir": "meaning, "They said he is crazy""
    }
  ]
}';
        $repaired = $this->service->repair($json);
        $decoded = json_decode($repaired, true);

        // Cleaned comparisons showing the actual content characters are preserved exactly
        $this->assertEquals('and said, "A madman,"', $decoded['segments'][0]['translation']);
        $this->assertEquals('meaning, "They said he is crazy"', $decoded['segments'][0]['tafsir']);
    }
}
