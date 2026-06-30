<?php

namespace App\Http\Controllers\Api;

use App\Modules\Quran\Models\RenderJob;
use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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
                $duration = $job->duration;

                // Cache fallback if duration is missing
                if ($duration === null || $duration <= 0.0) {
                    try {
                        $duration = $this->probeVideoDuration($absolutePath);
                        $job->update(['duration' => $duration]);
                    } catch (\Throwable $e) {
                        Log::warning("[VideoLibrary] Failed to fallback probe duration for {$job->output_filename}: " . $e->getMessage());
                        $duration = 0.0;
                    }
                }

                $videos[] = [
                    'filename'         => $job->output_filename,
                    'reciter'          => $job->reciter ? $job->reciter->name_arabic : 'غير معروف',
                    'reciter_en'       => $job->reciter ? $job->reciter->name_english : 'Unknown',
                    'surah'            => $job->surah_number,
                    'from_ayah'        => $job->from_ayah,
                    'to_ayah'          => $job->to_ayah,
                    'duration_seconds' => $duration,
                    'duration_human'   => $this->formatDurationHuman($duration),
                    'created_at'       => $job->updated_at->toIso8601String(),
                    'url'              => '/api/videos/' . $job->output_filename,
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

    /**
     * DELETE /api/videos/{filename}
     *
     * Delete the MP4 file and associated render job.
     */
    public function destroy(string $filename, QuranPathResolver $pathResolver): JsonResponse
    {
        $absolutePath = $pathResolver->videos($filename);

        if (!file_exists($absolutePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Video not found.'
            ], 404);
        }

        // Delete file from disk
        if (!unlink($absolutePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete the video file from disk.'
            ], 500);
        }

        // Delete associated render job
        RenderJob::where('output_filename', $filename)->delete();

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Run ffprobe to resolve duration of a video file.
     */
    private function probeVideoDuration(string $filePath): float
    {
        $ffprobe = config('ffmpeg.ffprobe_path', 'ffprobe');
        $cmd = sprintf(
            '"%s" -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "%s"',
            $ffprobe,
            $filePath
        );

        $output = [];
        $resultCode = -1;
        exec($cmd . ' 2>&1', $output, $resultCode);

        if ($resultCode !== 0 || count($output) === 0) {
            throw new \RuntimeException("ffprobe failed with exit code {$resultCode}.");
        }

        return (float) trim($output[0]);
    }

    /**
     * Format duration seconds to human-readable form MM:SS or HH:MM:SS.
     */
    private function formatDurationHuman(float $seconds): string
    {
        $intSecs = (int) floor($seconds);
        $h = floor($intSecs / 3600);
        $m = floor(($intSecs % 3600) / 60);
        $s = $intSecs % 60;

        if ($h > 0) {
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
        return sprintf('%02d:%02d', $m, $s);
    }
}
