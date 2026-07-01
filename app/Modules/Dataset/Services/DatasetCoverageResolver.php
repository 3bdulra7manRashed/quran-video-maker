<?php

namespace App\Modules\Dataset\Services;

use App\Modules\Quran\Models\DatasetMetadata;

class DatasetCoverageResolver
{
    /**
     * Resolve dataset coverage for a given reciter and surah.
     *
     * @param int $reciterId
     * @param int $surahNumber
     * @return array|null Null if whole surah (or no metadata), array ['from_ayah' => X, 'to_ayah' => Y] if partial.
     */
    public function resolve(int $reciterId, int $surahNumber): ?array
    {
        $metadata = DatasetMetadata::where('reciter_id', $reciterId)
            ->where('surah_number', $surahNumber)
            ->first();

        if ($metadata && $metadata->from_ayah !== null && $metadata->to_ayah !== null) {
            return [
                'from_ayah' => (int) $metadata->from_ayah,
                'to_ayah' => (int) $metadata->to_ayah,
            ];
        }

        return null;
    }
}
