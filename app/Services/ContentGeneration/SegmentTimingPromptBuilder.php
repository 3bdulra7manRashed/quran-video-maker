<?php

namespace App\Services\ContentGeneration;

use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\ReelsGeneratedContent;

class SegmentTimingPromptBuilder
{
    /**
     * Build the prompt for Segment Timing generation.
     *
     * @param int $surahNumber
     * @param string $reciterSlug
     * @param int|null $fromAyah
     * @param int|null $toAyah
     * @param string $rawMarkers
     * @return string
     */
    public function build(
        int $surahNumber,
        string $reciterSlug,
        ?int $fromAyah,
        ?int $toAyah,
        string $rawMarkers
    ): string {
        $templatePath = resource_path('prompts/reels_segment_timing.md');
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Prompt template reels_segment_timing.md not found at: {$templatePath}");
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

        // Fetch Reciter
        $reciter = Reciter::where('slug', $reciterSlug)->first();
        if (!$reciter) {
            throw new \RuntimeException("Reciter '{$reciterSlug}' not found.");
        }

        // Fetch approved segments from reels_generated_content
        $segmentsQuery = ReelsGeneratedContent::where('reciter_id', $reciter->id)
            ->where('surah_number', $surahNumber);
            
        // Note: Filter if segments can be scoped, but usually Reels content is surah-scoped.
        $segments = $segmentsQuery->orderBy('segment_order')->get();

        if ($segments->isEmpty()) {
            throw new \RuntimeException("No approved segment definitions found in the database. Please generate and approve segments first.");
        }

        // Format approved segments list
        $segmentsListLines = [];
        foreach ($segments as $seg) {
            $segmentsListLines[] = "Segment #{$seg->segment_order}: {$seg->arabic} | Translation: {$seg->translation}";
        }
        $segmentsListText = implode("\n", $segmentsListLines);

        $replacements = [
            '{{surah}}' => (string) $surahNumber,
            '{{from_ayah}}' => $fromAyah !== null ? (string) $fromAyah : '1',
            '{{to_ayah}}' => $toAyah !== null ? (string) $toAyah : 'null',
            '{{segments_list}}' => $segmentsListText,
            '{{markers}}' => $markersText,
        ];

        return strtr($template, $replacements);
    }
}
