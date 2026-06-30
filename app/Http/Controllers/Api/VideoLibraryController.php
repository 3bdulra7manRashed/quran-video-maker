<?php

namespace App\Http\Controllers\Api;

use App\Modules\Quran\Models\RenderJob;
use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoLibraryController
{
    /**
     * GET /api/videos
     *
     * List all completed videos from database that physically exist on disk.
     */
    public function index(QuranPathResolver $pathResolver): JsonResponse
    {
        $completedJobs = RenderJob::with('reciter')
            ->where('status', 'completed')
            ->orderBy('updated_at', 'desc')
            ->get();

        $videos = [];
        foreach ($completedJobs as $job) {
            if (!$job->output_filename) {
                continue;
            }

            $absolutePath = $pathResolver->videos($job->output_filename);

            if (file_exists($absolutePath)) {
                $videos[] = [
                    'filename'   => $job->output_filename,
                    'reciter'    => $job->reciter ? $job->reciter->name_arabic : 'غير معروف',
                    'reciter_en' => $job->reciter ? $job->reciter->name_english : 'Unknown',
                    'surah'      => $job->surah_number,
                    'from_ayah'  => $job->from_ayah,
                    'to_ayah'    => $job->to_ayah,
                    'created_at' => $job->updated_at->toIso8601String(),
                    'url'        => '/api/videos/' . $job->output_filename,
                ];
            }
        }

        return response()->json($videos);
    }

    /**
     * GET /api/videos/{filename}
     *
     * Stream or download the video file.
     */
    public function download(string $filename, QuranPathResolver $pathResolver)
    {
        $absolutePath = $pathResolver->videos($filename);

        if (!file_exists($absolutePath)) {
            return response()->json(['error' => 'Video file not found.'], 404);
        }

        return response()->file($absolutePath, [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
