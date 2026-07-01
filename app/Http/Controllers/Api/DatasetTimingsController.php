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

        // Validate structure based on type
        $isMushafLines = isset($data['type']) && $data['type'] === 'mushaf_lines';

        if ($isMushafLines) {
            if (!isset($data['surah']) || !isset($data['lines']) || !is_array($data['lines'])) {
                return response()->json([
                    'error' => 'JSON line timings file must contain: surah and lines array.',
                ], 422);
            }

            foreach ($data['lines'] as $line) {
                if (!isset($line['page_number']) || !isset($line['line_number']) || !isset($line['start'])) {
                    return response()->json([
                        'error' => 'Each line timing item must contain: page_number, line_number, and start.',
                    ], 422);
                }
            }

            // Strict QCF page range validation
            $surahNumber = (int) $data['surah'];
            $fromAyah = isset($data['from_ayah']) ? (int) $data['from_ayah'] : null;
            $toAyah = isset($data['to_ayah']) ? (int) $data['to_ayah'] : null;

            $surahModel = \App\Modules\Quran\Models\Surah::where('number', $surahNumber)->first();
            if (!$surahModel) {
                return response()->json([
                    'error' => "Surah {$surahNumber} not found in database.",
                ], 422);
            }

            $query = \App\Modules\Quran\Models\Word::whereHas('ayah', function ($q) use ($surahModel, $fromAyah, $toAyah) {
                $q->where('surah_id', $surahModel->id);
                if ($fromAyah !== null) {
                    $q->where('ayah_number', '>=', $fromAyah);
                }
                if ($toAyah !== null) {
                    $q->where('ayah_number', '<=', $toAyah);
                }
            });

            $expectedMin = $query->min('page_number');
            $expectedMax = $query->max('page_number');

            if ($expectedMin !== null && $expectedMax !== null && !empty($data['lines'])) {
                $uploadedPages = array_unique(array_map(fn($line) => (int) $line['page_number'], $data['lines']));
                $uploadedMin = min($uploadedPages);
                $uploadedMax = max($uploadedPages);

                if ($expectedMin !== $uploadedMin || $expectedMax !== $uploadedMax) {
                    $expectedStr = $expectedMin === $expectedMax ? (string)$expectedMin : "{$expectedMin}–{$expectedMax}";
                    $uploadedStr = $uploadedMin === $uploadedMax ? (string)$uploadedMin : "{$uploadedMin}–{$uploadedMax}";

                    $offsetStr = "";
                    if (($uploadedMin - $expectedMin) === ($uploadedMax - $expectedMax)) {
                        $offset = $uploadedMin - $expectedMin;
                        $offsetStr = " Detected constant page offset: {$offset}.";
                    }

                    return response()->json([
                        'message' => 'Uploaded page numbers do not match the QCF dataset.',
                        'errors' => [
                            'page_numbers' => [
                                "Expected pages: {$expectedStr}. Received pages: {$uploadedStr}.{$offsetStr}"
                            ]
                        ]
                    ], 422);
                }
            }
        } else {
            if (!isset($data['surah']) || !isset($data['from_ayah']) || !isset($data['to_ayah']) || !isset($data['words'])) {
                return response()->json([
                    'error' => 'JSON timings file must contain: surah, from_ayah, to_ayah, and words.',
                ], 422);
            }
        }

        try {
            if ($isMushafLines) {
                $report = $manualImporter->importLines($data, $reciter->id);
                return response()->json([
                    'success' => true,
                    'timedLines' => $report['timedLines'],
                ]);
            } else {
                $report = $manualImporter->import($data, $reciter->id);
                return response()->json([
                    'success' => true,
                    'timedWords' => $report['timedWords'],
                ]);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
