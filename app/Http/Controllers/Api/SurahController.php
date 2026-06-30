<?php

namespace App\Http\Controllers\Api;

use App\Modules\Quran\Models\Surah;
use Illuminate\Http\JsonResponse;

class SurahController
{
    /**
     * GET /api/surahs
     */
    public function index(): JsonResponse
    {
        // TODO: Future dataset listing roadmap endpoint
        // GET /api/datasets
        // Return format: [{"reciter": "yasser-al-dosari", "surah": 78, "renderable": true, "timings": "full"}]

        $surahs = Surah::orderBy('number', 'asc')->get();

        $formatted = $surahs->map(fn($s) => [
            'number' => $s->number,
            'name_arabic' => $s->name_arabic,
            'name_english' => $s->name_simple,
            'verses_count' => $s->verses_count,
        ]);

        return response()->json($formatted);
    }
}
