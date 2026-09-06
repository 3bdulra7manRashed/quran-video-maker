<?php

namespace App\Services\ContentGeneration;

use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Ayah;
use App\Modules\Quran\Models\AyahTranslation;
use App\Modules\Quran\Models\Word;
use App\Modules\Segmentation\Services\SegmentationService;

class PromptBuilder
{
    protected SegmentationService $segmentationService;

    public function __construct(SegmentationService $segmentationService)
    {
        $this->segmentationService = $segmentationService;
    }

    /**
     * Build the AI prompt text by compiling the template with localized inputs.
     *
     * @param int $surahNumber
     * @param string $reciterSlug
     * @param int|null $fromAyah
     * @param int|null $toAyah
     * @return string
     */
    public function build(
        int $surahNumber,
        string $reciterSlug,
        ?int $fromAyah = null,
        ?int $toAyah = null
    ): string {
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new \RuntimeException("Surah {$surahNumber} not found.");
        }

        $surahName = "Surah {$surahNumber} ({$surah->name_english} / {$surah->name_arabic})";
        $ayahScopeText = ($fromAyah && $toAyah) 
            ? "Ayahs {$fromAyah} to {$toAyah}" 
            : "Full Surah (Ayahs 1 to {$surah->verses_count})";

        // 1. Gather Ayahs & Words
        $ayahQuery = $surah->ayahs();
        if ($fromAyah !== null) {
            $ayahQuery->where('ayah_number', '>=', $fromAyah);
        }
        if ($toAyah !== null) {
            $ayahQuery->where('ayah_number', '<=', $toAyah);
        }
        $ayahs = $ayahQuery->orderBy('ayah_number')->get();
        $ayahIds = $ayahs->pluck('id');

        $words = Word::whereIn('ayah_id', $ayahIds)
            ->orderBy('page_number')
            ->orderBy('line_number')
            ->orderBy('ayah_id')
            ->orderBy('word_index')
            ->get();

        // 2. Format Arabic Text
        $arabicTextLines = [];
        foreach ($ayahs as $ayah) {
            $ayahWords = $words->filter(fn($w) => $w->ayah_id === $ayah->id);
            $wordsText = implode(' ', $ayahWords->pluck('uthmani_text')->all());
            $arabicTextLines[] = "{$wordsText} ({$ayah->ayah_number})";
        }
        $arabicText = implode("\n", $arabicTextLines);

        // 3. Format English Translation (Sahih International)
        $translations = AyahTranslation::where('source', 'sahih_international')
            ->where('surah_number', $surahNumber);
        if ($fromAyah !== null) {
            $translations->where('ayah_number', '>=', $fromAyah);
        }
        if ($toAyah !== null) {
            $translations->where('ayah_number', '<=', $toAyah);
        }
        $translationsMap = $translations->get()->keyBy('ayah_number');

        $translationLines = [];
        foreach ($ayahs as $ayah) {
            $t = $translationsMap[$ayah->ayah_number] ?? null;
            $text = $t ? $t->text : '';
            $translationLines[] = "{$ayah->ayah_number}: {$text}";
        }
        $translationText = implode("\n", $translationLines);

        // 4. Format Tafsir
        $tafsirLines = [];
        foreach ($ayahs as $ayah) {
            $filePath = storage_path("app/quran/tafsir/tafsir_{$surahNumber}_{$ayah->ayah_number}.json");
            if (file_exists($filePath)) {
                $json = json_decode(file_get_contents($filePath), true);
                $text = $json['tafsir']['text'] ?? null;
                if ($text) {
                    $tafsirLines[] = strip_tags($text);
                    continue;
                }
            }
            $tafsirLines[] = "No official tafsir available for this verse.";
        }
        $tafsirText = implode("\n", $tafsirLines);

        // 5. Initial Dynamic Segments
        $reciter = \App\Modules\Quran\Models\Reciter::findBySlug($reciterSlug);
        // Fallback or dynamic segmentation using standard timing
        $segments = $this->segmentationService->segment($words->all(), 10.0, 'reels', 1, $reciter, $surahNumber);
        
        $segmentLines = [];
        foreach ($segments as $idx => $seg) {
            $segArabic = implode(' ', array_map(fn($w) => $w->uthmani_text, $seg->words));
            $segmentLines[] = "Segment " . ($idx + 1) . ": {$segArabic}";
        }
        $segmentsText = implode("\n", $segmentLines);

        // 6. Layout constraints & placeholders
        $layoutConstraints = "- Vertical Reels layout (1080x1920)\n" .
                             "- Limit each segment to one line of Arabic text (max 4-6 words)\n" .
                             "- Keep segment counts reasonable\n" .
                             "- Each Arabic segment must have a corresponding English translation and Tafsir segment\n" .
                             "- The total Arabic text must match the input exactly\n" .
                             "- The translation and tafsir parts must be split proportionally to match the Arabic segment's meaning";

        $templatePath = resource_path('prompts/reels_segment_content.md');
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Prompt template reels_segment_content.md not found at: {$templatePath}");
        }
        $template = file_get_contents($templatePath);

        $replacements = [
            '{{surah}}' => $surahName,
            '{{ayahs}}' => $ayahScopeText,
            '{{arabic}}' => $arabicText,
            '{{translation}}' => $translationText,
            '{{tafsir}}' => $tafsirText,
            '{{segments}}' => $segmentsText,
            '{{layout_constraints}}' => $layoutConstraints,
        ];

        $output = strtr($template, $replacements);

        $strictInstructions = <<<'PROMPT'


## Output Format (STRICT)

Return your answer as a single valid JSON object wrapped inside exactly one Markdown JSON code block.

Example:

```json
{
  "segments": [
    {
      "order": 1,
      "arabic": "...",
      "translation": "...",
      "tafsir": "..."
    }
  ]
}
```

Rules:

- Return exactly one Markdown `json` code block and absolutely nothing else.
- Do not return explanations, notes, headings, comments, markdown lists, or prose before or after the JSON.
- All JSON keys and string values must use double quotes (`"`).
- Escape all quotation marks inside strings correctly using `\"`.
- Do not include trailing commas.
- Preserve all provided Arabic, Sahih International translation, and tafsir text exactly as given.
- The Sahih International translation must be preserved verbatim and only split proportionally according to the Arabic segmentation.
- Do not rewrite, paraphrase, invent, summarize, or modify any text.
- The response must be valid JSON that can be parsed directly.

Before returning your answer, internally verify that:

- The JSON can be parsed successfully by `JSON.parse()`.
- All quotation marks inside strings are properly escaped.
- All commas, brackets, and braces are syntactically valid.
- The response contains exactly one Markdown `json` code block and absolutely nothing else.

If any check fails, regenerate the entire response until it is valid.
PROMPT;

        return $output . $strictInstructions;
    }
}
