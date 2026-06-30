<?php

namespace App\Http\Controllers\Api;

use App\Modules\Quran\Models\Reciter;
use Illuminate\Http\JsonResponse;

class ReciterController
{
    /**
     * GET /api/reciters
     */
    public function index(): JsonResponse
    {
        $reciters = Reciter::orderBy('name_arabic')
            ->get(['id', 'slug', 'name_arabic', 'name_english']);

        return response()->json($reciters);
    }
}
