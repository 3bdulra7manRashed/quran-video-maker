<?php

namespace App\Http\Controllers\Api;

use App\Modules\Dataset\Services\DatasetStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetStatusController
{
    protected DatasetStatusService $datasetStatusService;

    public function __construct(DatasetStatusService $datasetStatusService)
    {
        $this->datasetStatusService = $datasetStatusService;
    }

    /**
     * GET /api/datasets/status?reciter=<slug>&surah=<number>
     */
    public function status(Request $request): JsonResponse
    {
        $reciterSlug = $request->query('reciter');
        $surahNumber = $request->query('surah');

        if (!$reciterSlug || !$surahNumber) {
            return response()->json([
                'error' => 'Both "reciter" and "surah" query parameters are required.',
            ], 422);
        }

        $surahNumber = (int) $surahNumber;

        $result = $this->datasetStatusService->evaluate($reciterSlug, $surahNumber);

        return response()->json($result);
    }
}
