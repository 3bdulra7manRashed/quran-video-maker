<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ContentGeneration\ContentNormalizer;

class ContentNormalizerTest extends TestCase
{
    /**
     * Test acceptance criteria matching successfully.
     */
    public function test_normalize_for_matching_acceptance_criteria(): void
    {
        // 1. Ayah numbers: "وَٱزْدُجِرَ ٩" ↔ "وَٱزْدُجِرَ"
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("وَٱزْدُجِرَ ٩"),
            ContentNormalizer::normalizeForMatching("وَٱزْدُجِرَ")
        );

        // 2. Alif madda: "فَفَتَحْنَآ" ↔ "فَفَتَحْنَا"
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("فَفَتَحْنَآ"),
            ContentNormalizer::normalizeForMatching("فَفَتَحْنَا")
        );

        // 3. Alif forms: "ٱلْمَآءُ" ↔ "ٱلْمَاءُ"
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("ٱلْمَآءُ"),
            ContentNormalizer::normalizeForMatching("ٱلْمَاءُ")
        );

        // 4. Tashkeel / diacritics: "كَذَّبَتْ" ↔ "كَذبت"
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("كَذَّبَتْ"),
            ContentNormalizer::normalizeForMatching("كَذبت")
        );
    }

    /**
     * Test individual rules specifically.
     */
    public function test_remove_ayah_numbers(): void
    {
        $this->assertEquals("ا", ContentNormalizer::normalizeForMatching("ا ١٢٣٤٥٦٧٨٩٠"));
        $this->assertEquals("ب", ContentNormalizer::normalizeForMatching("ب 12345"));
    }

    public function test_remove_tashkeel_and_combining_marks(): void
    {
        // Remove fatha, damma, kasra, shadda, sukun
        $this->assertEquals("كتب", ContentNormalizer::normalizeForMatching("كَتَبَ"));
    }

    public function test_remove_quranic_stop_marks(): void
    {
        $this->assertEquals("كلا", ContentNormalizer::normalizeForMatching("كلا ۞ ۚ ۖ ۗ ۘ ۙ ۛ ۢ ۭ"));
    }

    public function test_normalize_alif_forms(): void
    {
        $this->assertEquals("ا ا ا ا", ContentNormalizer::normalizeForMatching("آ أ إ ٱ"));
    }

    public function test_normalize_ya_and_tatweel(): void
    {
        // ى -> ي
        $this->assertEquals("علي", ContentNormalizer::normalizeForMatching("على"));
        // tatweel removal
        $this->assertEquals("فتحنا", ContentNormalizer::normalizeForMatching("فـتـحـنا"));
    }

    public function test_normalize_required_variations(): void
    {
        // أَنِّى ↔ اني
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("أَنِّى"),
            ContentNormalizer::normalizeForMatching("اني")
        );

        // ٱلسَّمَآءِ ↔ السماء
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("ٱلسَّمَآءِ"),
            ContentNormalizer::normalizeForMatching("السماء")
        );

        // فَفَتَحْنَآ ↔ ففتحنا
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("فَفَتَحْنَآ"),
            ContentNormalizer::normalizeForMatching("ففتحنا")
        );

        // Test 1: ءايه ↔ ءاية
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("ءايه"),
            ContentNormalizer::normalizeForMatching("ءاية")
        );

        // Test 2: مدينه ↔ مدينة
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("مدينه"),
            ContentNormalizer::normalizeForMatching("مدينة")
        );

        // Test 3: الرحمه ↔ الرحمة
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("الرحمه"),
            ContentNormalizer::normalizeForMatching("الرحمة")
        );
    }

    public function test_collapse_spaces_and_trim(): void
    {
        $this->assertEquals("ا ب ج", ContentNormalizer::normalizeForMatching("  ا   ب   ج   "));
    }

    public function test_normalize_layl_orthography_variations(): void
    {
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("وَٱلَّيْلِ إِذَا سَجَىٰ"),
            ContentNormalizer::normalizeForMatching("وَاللَّيْلِ إِذَا سَجَى")
        );
        $this->assertEquals(
            ContentNormalizer::normalizeForMatching("ٱلَّيْلِ"),
            ContentNormalizer::normalizeForMatching("الليل")
        );
    }
}
