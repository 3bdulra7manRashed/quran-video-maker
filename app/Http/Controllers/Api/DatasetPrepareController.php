<?php

namespace App\Http\Controllers\Api;

use App\Jobs\PrepareDatasetJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class DatasetPrepareController
{
    /**
     * POST /api/datasets/prepare
     */
    public function prepare(Request $request): JsonResponse
    {
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

        $cacheKey = "dataset_prepare_{$reciterSlug}_{$surahNumber}";
        $existing = Cache::get($cacheKey);

        // If already queued or downloading, return current status without double-queueing
        if ($existing && in_array($existing['status'] ?? '', ['queued', 'downloading'], true)) {
            return response()->json([
                'status'  => $existing['status'],
                'message' => $existing['message'] ?? 'جاري تجهيز بيانات وتلاوة السورة بالفعل...',
            ]);
        }

        Cache::put($cacheKey, [
            'status'     => 'queued',
            'message'    => 'بدأ تجهيز وتنزيل ملفات السورة في الخلفية...',
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        PrepareDatasetJob::dispatch($reciterSlug, $surahNumber);

        return response()->json([
            'status'  => 'queued',
            'message' => 'بدأ تجهيز وتنزيل ملفات السورة، يمكنك المتابعة وسننبهك فور الانتهاء.',
        ]);
    }
}
