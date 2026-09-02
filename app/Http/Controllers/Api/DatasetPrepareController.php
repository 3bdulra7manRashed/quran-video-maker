<?php

namespace App\Http\Controllers\Api;

use App\Jobs\PrepareDatasetJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DatasetPrepareController
{
    /**
     * POST /api/datasets/prepare
     */
    public function prepare(Request $request): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validator = Validator::make($request->all(), [
            'reciter' => 'required|string|exists:reciters,slug',
            'surah'   => 'required|integer|exists:surahs,number',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $reciterSlug = $request->input('reciter');
        $surahNumber = (int) $request->input('surah');

        $jobId = app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch(
            new PrepareDatasetJob($reciterSlug, $surahNumber)
        );

        $response = [
            'status' => 'processing',
        ];

        // Only return real trackable job ID if driver is capable and returned a scalar ID
        if (is_scalar($jobId) && $jobId !== null) {
            $response['job_id'] = $jobId;
        }

        return response()->json($response);
    }
}
