<?php

namespace App\Http\Controllers\Api;

use App\Modules\Quran\Models\Reciter;
use App\Modules\Import\Services\ImportManualTimingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DatasetTimingsController
{
    /**
     * POST /api/datasets/timings
     */
    public function upload(Request $request, ImportManualTimingsService $manualImporter): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reciter' => 'required|string|exists:reciters,slug',
            'file'    => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $reciterSlug = $request->input('reciter');
        $file = $request->file('file');

        if ($file->getClientOriginalExtension() !== 'json' && $file->getMimeType() !== 'application/json') {
            return response()->json([
                'error' => 'The uploaded file must be a JSON timing file.',
            ], 422);
        }

        $reciter = Reciter::where('slug', $reciterSlug)->first();
        if (!$reciter) {
            return response()->json([
                'error' => "Reciter '{$reciterSlug}' not found.",
            ], 404);
        }

        // Read and decode JSON content
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'error' => 'Invalid JSON structure: ' . json_last_error_msg(),
            ], 422);
        }

        // Validate structure
        if (!isset($data['surah']) || !isset($data['from_ayah']) || !isset($data['to_ayah']) || !isset($data['words'])) {
            return response()->json([
                'error' => 'JSON timings file must contain: surah, from_ayah, to_ayah, and words.',
            ], 422);
        }

        try {
            $report = $manualImporter->import($data, $reciter->id);
            return response()->json([
                'success' => true,
                'timedWords' => $report['timedWords'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
