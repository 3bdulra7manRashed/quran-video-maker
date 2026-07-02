<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ContentGeneration\PromptBuilder;
use App\Services\ContentGeneration\ContentValidator;
use App\Services\ContentGeneration\ContentNormalizer;
use App\Modules\Quran\Models\ReelsGeneratedContent;
use App\Modules\Quran\Models\Reciter;
use App\Enums\ContentApprovalStatus;
use App\Enums\GeneratorType;
use App\Enums\LayoutType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContentGenerationController extends Controller
{
    protected PromptBuilder $promptBuilder;

    public function __construct(PromptBuilder $promptBuilder)
    {
        $this->promptBuilder = $promptBuilder;
    }

    /**
     * Generate segment prompt text.
     */
    public function generatePrompt(Request $request)
    {
        $request->validate([
            'reciter' => 'required|string',
            'surah' => 'required|integer',
            'from_ayah' => 'nullable|integer',
            'to_ayah' => 'nullable|integer',
        ]);

        $reciterSlug = $request->input('reciter');
        $surahNumber = (int)$request->input('surah');
        $fromAyah = $request->input('from_ayah') ? (int)$request->input('from_ayah') : null;
        $toAyah = $request->input('to_ayah') ? (int)$request->input('to_ayah') : null;

        try {
            $prompt = $this->promptBuilder->build($surahNumber, $reciterSlug, $fromAyah, $toAyah);
            return response()->json(['prompt' => $prompt]);
        } catch (\Exception $e) {
            Log::error('Prompt generation failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Generate Mushaf line timing prompt text.
     */
    public function generateMushafPrompt(Request $request, \App\Services\ContentGeneration\MushafLineTimingPromptBuilder $mushafBuilder)
    {
        $request->validate([
            'surah' => 'required|integer',
            'from_ayah' => 'nullable|integer',
            'to_ayah' => 'nullable|integer',
            'start_page' => 'required|integer',
            'start_line' => 'required|integer',
            'markers' => 'required|string',
        ]);

        $surahNumber = (int)$request->input('surah');
        $fromAyah = $request->input('from_ayah') ? (int)$request->input('from_ayah') : null;
        $toAyah = $request->input('to_ayah') ? (int)$request->input('to_ayah') : null;
        $startPage = (int)$request->input('start_page');
        $startLine = (int)$request->input('start_line');
        $markers = $request->input('markers');

        try {
            $prompt = $mushafBuilder->build($surahNumber, $fromAyah, $toAyah, $startPage, $startLine, $markers);
            return response()->json(['prompt' => $prompt]);
        } catch (\Exception $e) {
            Log::error('Mushaf prompt generation failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Validate and preview JSON payload.
     */
    public function previewImport(Request $request)
    {
        $request->validate([
            'json' => 'required|string',
            'surah' => 'required|integer',
            'from_ayah' => 'nullable|integer',
            'to_ayah' => 'nullable|integer',
        ]);

        $jsonStr = $request->input('json');
        $surahNumber = (int)$request->input('surah');
        $fromAyah = $request->input('from_ayah') ? (int)$request->input('from_ayah') : null;
        $toAyah = $request->input('to_ayah') ? (int)$request->input('to_ayah') : null;

        $originalParseError = null;
        $repaired = false;
        $repairedJson = null;

        $data = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $originalParseError = json_last_error_msg();
            Log::warning("AI JSON parse failed: {$originalParseError}. Attempting repair.");

            $repairService = new \App\Services\ContentGeneration\AiJsonRepairService();
            $repairedJson = $repairService->repair($jsonStr);
            $data = json_decode($repairedJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("AI JSON repair failed: " . json_last_error_msg());
                return response()->json([
                    'isValid' => false,
                    'errors' => ['Invalid JSON. The AI response is severely malformed and could not be repaired automatically.'],
                    'warnings' => [],
                ], 200);
            }

            Log::info("AI JSON repair succeeded.");
            $repaired = true;
        }

        $result = ContentValidator::validate($data, $surahNumber, $fromAyah, $toAyah);
        
        if ($repaired) {
            if (!isset($result['warnings'])) {
                $result['warnings'] = [];
            }
            $result['warnings'][] = 'AI response contained malformed quotation marks and was repaired automatically.';
            $result['repairedJson'] = $repairedJson;
        }

        // Normalize fields for preview visual representation
        if ($result['isValid'] && isset($data['segments'])) {
            foreach ($data['segments'] as &$seg) {
                $seg['arabic'] = ContentNormalizer::normalizeArabic($seg['arabic']);
                $seg['translation'] = ContentNormalizer::normalizeTranslation($seg['translation']);
                $seg['tafsir'] = ContentNormalizer::normalizeTafsir($seg['tafsir']);
            }
            $result['segments'] = $data['segments'];
        }

        return response()->json($result);
    }

    /**
     * Approve and save segment list inside database transaction with archiving.
     */
    public function approveImport(Request $request)
    {
        $request->validate([
            'json' => 'required|string',
            'reciter' => 'required|string',
            'surah' => 'required|integer',
            'from_ayah' => 'nullable|integer',
            'to_ayah' => 'nullable|integer',
            'generator_type' => 'required|string',
            'generator_model' => 'nullable|string',
            'generator_latency_ms' => 'nullable|integer',
            'prompt_version' => 'nullable|string',
            'prompt_hash' => 'nullable|string',
        ]);

        $jsonStr = $request->input('json');
        $reciterSlug = $request->input('reciter');
        $surahNumber = (int)$request->input('surah');
        $fromAyah = $request->input('from_ayah') ? (int)$request->input('from_ayah') : null;
        $toAyah = $request->input('to_ayah') ? (int)$request->input('to_ayah') : null;
        
        $generatorTypeStr = $request->input('generator_type');
        $generatorModel = $request->input('generator_model');
        $latency = $request->input('generator_latency_ms') ? (int)$request->input('generator_latency_ms') : null;
        $promptVersion = $request->input('prompt_version');
        $promptHash = $request->input('prompt_hash');

        $reciter = Reciter::where('slug', $reciterSlug)->first();
        if (!$reciter) {
            return response()->json(['error' => "Reciter '{$reciterSlug}' not found."], 400);
        }

        $data = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("AI JSON parse failed in approveImport. Attempting repair.");
            $repairService = new \App\Services\ContentGeneration\AiJsonRepairService();
            $jsonStr = $repairService->repair($jsonStr);
            $data = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Invalid JSON structure.'], 400);
            }
        }

        $result = ContentValidator::validate($data, $surahNumber, $fromAyah, $toAyah);
        if (!$result['isValid']) {
            return response()->json(['error' => 'Validation failed.', 'details' => $result['errors']], 400);
        }

        $startAyah = $fromAyah ?? 1;
        $endAyah = $toAyah ?? 999;
        if ($toAyah === null) {
            $surah = \App\Modules\Quran\Models\Surah::where('number', $surahNumber)->first();
            $endAyah = $surah ? $surah->verses_count : 114;
        }

        // Parse Enums
        $layoutType = LayoutType::REELS; // Reels Layout v1.0 only
        $generatorType = GeneratorType::tryFrom($generatorTypeStr) ?? GeneratorType::MANUAL;

        DB::beginTransaction();
        try {
            // Find the highest content_version currently approved or archived for this key
            $maxVersion = ReelsGeneratedContent::where('reciter_id', $reciter->id)
                ->where('surah_number', $surahNumber)
                ->where('start_ayah', $startAyah)
                ->where('end_ayah', $endAyah)
                ->where('layout_type', $layoutType)
                ->max('content_version') ?? 0;

            $newVersion = $maxVersion + 1;

            // Archive all currently approved entries for this key
            ReelsGeneratedContent::where('reciter_id', $reciter->id)
                ->where('surah_number', $surahNumber)
                ->where('start_ayah', $startAyah)
                ->where('end_ayah', $endAyah)
                ->where('layout_type', $layoutType)
                ->where('approval_status', ContentApprovalStatus::APPROVED)
                ->update(['approval_status' => ContentApprovalStatus::ARCHIVED]);

            // Save new approved entries
            foreach ($data['segments'] as $seg) {
                ReelsGeneratedContent::create([
                    'reciter_id' => $reciter->id,
                    'surah_number' => $surahNumber,
                    'start_ayah' => $startAyah,
                    'end_ayah' => $endAyah,
                    'layout_type' => $layoutType,
                    'segment_order' => (int)$seg['order'],
                    'arabic' => ContentNormalizer::normalizeArabic($seg['arabic']),
                    'translation' => ContentNormalizer::normalizeTranslation($seg['translation']),
                    'tafsir' => ContentNormalizer::normalizeTafsir($seg['tafsir']),
                    'approval_status' => ContentApprovalStatus::APPROVED,
                    'content_version' => $newVersion,
                    'generator_type' => $generatorType,
                    'generator_model' => $generatorModel,
                    'generator_latency_ms' => $latency,
                    'prompt_version' => $promptVersion,
                    'prompt_hash' => $promptHash,
                    'source_json' => $data, // Store original raw JSON payload exactly
                    'generated_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'version' => $newVersion]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save approved generated content: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save generated content to database: ' . $e->getMessage()], 500);
        }
    }
}
