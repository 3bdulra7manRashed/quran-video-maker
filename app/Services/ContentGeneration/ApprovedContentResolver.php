<?php

namespace App\Services\ContentGeneration;

use App\Modules\Quran\Models\ReelsGeneratedContent;
use App\Enums\ContentApprovalStatus;
use Illuminate\Support\Collection;

class ApprovedContentResolver
{
    /**
     * Resolve the latest approved pre-generated content.
     *
     * @param int $reciterId
     * @param int $surahNumber
     * @param int|null $fromAyah
     * @param int|null $toAyah
     * @param string $layout
     * @return Collection|null
     */
    public function resolve(
        int $reciterId,
        int $surahNumber,
        ?int $fromAyah = null,
        ?int $toAyah = null,
        string $layout = 'reels'
    ): ?Collection {
        $startAyah = $fromAyah ?? 1;
        $endAyah = $toAyah ?? 999; // If toAyah is null, we check the query bounds

        // Determine the actual end_ayah in DB if toAyah was null
        if ($toAyah === null) {
            $surah = \App\Modules\Quran\Models\Surah::where('number', $surahNumber)->first();
            $endAyah = $surah ? $surah->verses_count : 114;
        }

        // Determine highest approved content_version to prevent duplicate or stale segments
        $latestVersion = ReelsGeneratedContent::where('reciter_id', $reciterId)
            ->where('surah_number', $surahNumber)
            ->where('start_ayah', $startAyah)
            ->where('end_ayah', $endAyah)
            ->where('layout_type', $layout)
            ->where('approval_status', ContentApprovalStatus::APPROVED)
            ->max('content_version');

        if ($latestVersion === null) {
            return null;
        }

        $records = ReelsGeneratedContent::where('reciter_id', $reciterId)
            ->where('surah_number', $surahNumber)
            ->where('start_ayah', $startAyah)
            ->where('end_ayah', $endAyah)
            ->where('layout_type', $layout)
            ->where('approval_status', ContentApprovalStatus::APPROVED)
            ->where('content_version', $latestVersion)
            ->orderBy('segment_order')
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        return $records;
    }
}
