<?php

namespace App\Services\ContentGeneration;

class MushafLineTimingPromptBuilder
{
    /**
     * Build the prompt for Mushaf line timing generation.
     *
     * @param int $surahNumber
     * @param int|null $fromAyah
     * @param int|null $toAyah
     * @param int $startPage
     * @param int $startLine
     * @param string $rawMarkers
     * @return string
     */
    public function build(
        int $surahNumber,
        ?int $fromAyah,
        ?int $toAyah,
        int $startPage,
        int $startLine,
        string $rawMarkers
    ): string {
        $templatePath = resource_path('prompts/mushaf_line_timing.md');
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Prompt template mushaf_line_timing.md not found at: {$templatePath}");
        }
        $template = file_get_contents($templatePath);

        // Process markers: split by newlines, trim, and rejoin with newlines
        $markerLines = explode("\n", $rawMarkers);
        $cleanedMarkers = [];
        foreach ($markerLines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $cleanedMarkers[] = $line;
            }
        }
        $markersText = implode("\n", $cleanedMarkers);

        $replacements = [
            '{{surah}}' => (string) $surahNumber,
            '{{from_ayah}}' => $fromAyah !== null ? (string) $fromAyah : 'null',
            '{{to_ayah}}' => $toAyah !== null ? (string) $toAyah : 'null',
            '{{start_page}}' => (string) $startPage,
            '{{start_line}}' => (string) $startLine,
            '{{markers}}' => $markersText,
        ];

        return strtr($template, $replacements);
    }
}
