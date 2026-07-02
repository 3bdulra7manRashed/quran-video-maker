<?php

namespace App\Http\Controllers\Api;

use App\Jobs\GenerateVideoJob;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\RenderJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DatasetRenderController
{
    /**
     * POST /api/renders
     *
     * Create a new render job and push to queue.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reciter'            => 'required|string|exists:reciters,slug',
            'surah'              => 'required|integer|exists:surahs,number',
            'scope'              => ['required', 'string', Rule::in(['full', 'single', 'range'])],
            'ayah'               => 'required_if:scope,single|integer|min:1',
            'from'               => 'required_if:scope,range|integer|min:1',
            'to'                 => 'required_if:scope,range|integer|min:1',
            'layout'             => 'sometimes|string|in:reels,youtube',
            'max_lines'          => 'sometimes|integer|min:1',
            'with_translation'   => 'sometimes|boolean',
            'translation_source' => 'sometimes|string',
            'use_generated_content' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reciterSlug = $request->input('reciter');
        $surahNumber = (int) $request->input('surah');
        $scope = $request->input('scope');
        $layout = $request->input('layout', 'reels');
        $maxLines = (int) $request->input('max_lines', 1);

        $reciter = Reciter::where('slug', $reciterSlug)->firstOrFail();
        $surah = Surah::where('number', $surahNumber)->firstOrFail();

        $fromAyah = null;
        $toAyah = null;

        if ($scope === 'single') {
            $ayah = (int) $request->input('ayah');
            if ($ayah > $surah->verses_count) {
                return response()->json(['error' => "Ayah number {$ayah} exceeds Surah verses count ({$surah->verses_count})."], 422);
            }
            $fromAyah = $ayah;
            $toAyah = $ayah;
        } elseif ($scope === 'range') {
            $from = (int) $request->input('from');
            $to = (int) $request->input('to');
            if ($from > $to) {
                return response()->json(['error' => "From ayah ({$from}) must be less than or equal to To ayah ({$to})."], 422);
            }
            if ($to > $surah->verses_count) {
                return response()->json(['error' => "To ayah ({$to}) exceeds Surah verses count ({$surah->verses_count})."], 422);
            }
            $fromAyah = $from;
            $toAyah = $to;
        }

        $withTranslation = (bool) $request->input('with_translation', false);
        $translationSource = $withTranslation ? $request->input('translation_source', 'sahih_international') : null;
        $useGeneratedContent = (bool) $request->input('use_generated_content', false);

        // Create the RenderJob model record
        $renderJob = RenderJob::create([
            'status'             => 'pending',
            'reciter_id'         => $reciter->id,
            'surah_number'       => $surah->number,
            'layout'             => $layout,
            'max_lines'          => $maxLines,
            'from_ayah'          => $fromAyah,
            'to_ayah'            => $toAyah,
            'progress'           => 0,
            'with_translation'   => $withTranslation,
            'translation_source' => $translationSource,
            'use_generated_content' => $useGeneratedContent,
        ]);

        // Dispatch GenerateVideoJob to queue
        GenerateVideoJob::dispatch($renderJob);

        return response()->json([
            'uuid'   => $renderJob->uuid,
            'status' => 'queued',
        ]);
    }

    /**
     * GET /api/renders/{uuid}
     *
     * Retrieve status of a single render job.
     */
    public function show(string $uuid): JsonResponse
    {
        $job = RenderJob::where('uuid', $uuid)->first();
        if (!$job) {
            return response()->json(['error' => 'Render job not found.'], 404);
        }

        // Map database status ('pending', 'processing') -> API contract ('queued', 'running')
        $status = match ($job->status) {
            'pending' => 'queued',
            'processing' => 'running',
            default => $job->status,
        };

        $response = [
            'uuid'               => $job->uuid,
            'status'             => $status,
            'progress'           => $job->progress,
            'with_translation'   => (bool) ($job->with_translation ?? false),
            'translation_source' => $job->translation_source,
            'use_generated_content' => (bool) ($job->use_generated_content ?? false),
        ];

        if ($status === 'completed') {
            $response['video'] = [
                'filename' => $job->output_filename,
                'url'      => '/api/videos/' . $job->output_filename,
            ];
        } elseif ($status === 'failed') {
            $response['error'] = $job->error_message ?? 'Unknown error occurred during rendering.';
        }

        return response()->json($response);
    }

    /**
     * GET /api/renders
     *
     * List all render jobs (efficient polling target).
     */
    public function index(): JsonResponse
    {
        $jobs = RenderJob::with('reciter')
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $jobs->map(function ($job) {
            $status = match ($job->status) {
                'pending' => 'queued',
                'processing' => 'running',
                default => $job->status,
            };

            $res = [
                'uuid'               => $job->uuid,
                'status'             => $status,
                'reciter'            => $job->reciter ? $job->reciter->name_english : 'Unknown',
                'reciter_ar'         => $job->reciter ? $job->reciter->name_arabic : 'غير معروف',
                'surah'              => $job->surah_number,
                'layout'             => $job->layout ?? 'reels',
                'max_lines'          => $job->max_lines ?? 1,
                'from_ayah'          => $job->from_ayah,
                'to_ayah'            => $job->to_ayah,
                'progress'           => $job->progress,
                'created_at'         => $job->created_at->toIso8601String(),
                'error'              => $job->error_message,
                'with_translation'   => (bool) ($job->with_translation ?? false),
                'translation_source' => $job->translation_source,
                'use_generated_content' => (bool) ($job->use_generated_content ?? false),
            ];

            if ($status === 'completed') {
                $res['filename'] = $job->output_filename;
                $res['url'] = '/api/videos/' . $job->output_filename;
            }

            return $res;
        });

        return response()->json($formatted);
    }

    // TODO: Roadmap implementation: POST /api/renders/{uuid}/retry
    // For retrying failed jobs:
    // public function retry(string $uuid): JsonResponse { ... }

    /**
     * Cancel a render job via UUID.
     *
     * POST /api/renders/{uuid}/cancel
     */
    public function cancel(string $uuid): JsonResponse
    {
        $job = RenderJob::where('uuid', $uuid)->first();
        if (!$job) {
            return response()->json(['error' => 'Render job not found.'], 404);
        }
        return $this->cancelJob($job);
    }

    /**
     * Cancel a render job via database ID or UUID.
     *
     * POST /api/render-jobs/{id}/cancel
     */
    public function cancelById(string $id): JsonResponse
    {
        $job = RenderJob::where('id', $id)->orWhere('uuid', $id)->first();
        if (!$job) {
            return response()->json(['error' => 'Render job not found.'], 404);
        }
        return $this->cancelJob($job);
    }

    /**
     * Common method to process cancellation transitions.
     */
    protected function cancelJob(RenderJob $job): JsonResponse
    {
        if ($job->isCompleted()) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot cancel a completed job.',
                'status' => $job->status
            ], 422);
        }

        if ($job->isFailed()) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot cancel a failed job.',
                'status' => $job->status
            ], 422);
        }

        if ($job->isCancelled()) {
            return response()->json([
                'success' => true,
                'status' => 'cancelled'
            ]);
        }

        if ($job->isPending()) {
            $job->update([
                'status' => 'cancelled',
                'progress' => 0,
                'finished_at' => now(),
                'completed_at' => now(),
            ]);
            return response()->json([
                'success' => true,
                'status' => 'cancelled'
            ]);
        }

        if ($job->isProcessing()) {
            $job->update([
                'status' => 'cancelling',
            ]);
            return response()->json([
                'success' => true,
                'status' => 'cancelling'
            ]);
        }

        if ($job->isCancelling()) {
            return response()->json([
                'success' => true,
                'status' => 'cancelling'
            ]);
        }

        return response()->json(['error' => 'Invalid job status.'], 400);
    }
}
