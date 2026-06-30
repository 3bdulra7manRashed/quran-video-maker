<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VideoComposer
{
    protected QuranPathResolver $pathResolver;

    public function __construct(QuranPathResolver $pathResolver)
    {
        $this->pathResolver = $pathResolver;
    }

    /**
     * Compose a sequence of frame images with durations and an audio track into an MP4 video using FFmpeg.
     *
     * @param array $frames Array of arrays containing 'image_path' and 'duration'.
     * @param string $audioPath Absolute path to the audio MP3.
     * @param string $outputPath Absolute path to the output MP4.
     * @param float|null $totalDuration Total duration in seconds to constrain video encoding.
     * @param float|null $audioStartSec Optional seek start timestamp in seconds.
     * @param float|null $audioDurationSec Optional duration limit in seconds for the audio stream.
     * @return void
     */
    public function compose(
        array $frames,
        string $audioPath,
        string $outputPath,
        ?float $totalDuration = null,
        ?float $audioStartSec = null,
        ?float $audioDurationSec = null
    ): void {
        Log::info("[VideoComposer] Starting video composition", [
            'frames_count' => count($frames),
            'audio' => $audioPath,
            'output' => $outputPath,
            'duration_limit' => $totalDuration,
            'audio_start_sec' => $audioStartSec,
            'audio_duration_sec' => $audioDurationSec,
        ]);

        if (empty($frames)) {
            throw new RuntimeException("No frames provided for video composition.");
        }

        if (!file_exists($audioPath)) {
            throw new RuntimeException("Audio file not found: {$audioPath}");
        }

        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 1. Generate FFmpeg concat demuxer file
        $tempDir = $this->pathResolver->temp();

        $concatFilePath = $tempDir . DIRECTORY_SEPARATOR . 'concat_' . uniqid() . '.txt';
        $concatContent = "";

        foreach ($frames as $frame) {
            $imagePath = str_replace('\\', '/', $frame['image_path']);
            // Escape single quotes for FFmpeg file path
            $escapedPath = str_replace("'", "'\\''", $imagePath);
            $concatContent .= "file '" . $escapedPath . "'\n";
            $concatContent .= "duration " . $frame['duration'] . "\n";
        }

        // Concat demuxer requires repeating the last file entry at the end without duration
        $lastFrame = end($frames);
        $lastImagePath = str_replace('\\', '/', $lastFrame['image_path']);
        $escapedLastPath = str_replace("'", "'\\''", $lastImagePath);
        $concatContent .= "file '" . $escapedLastPath . "'\n";

        file_put_contents($concatFilePath, $concatContent);

        // 2. Build FFmpeg command using configured path
        $ffmpeg = config('ffmpeg.ffmpeg_path', 'ffmpeg');

        // Target CFR 30 FPS. Specifying input duration limits encoding.
        $durationArgs = "";
        if ($totalDuration !== null) {
            $durationArgs = "-t " . esc_args(sprintf("%.6f", $totalDuration));
        }

        // Apply seeking and trimming to the audio input if requested
        $audioInputArgs = "";
        if ($audioStartSec !== null && $audioDurationSec !== null && $audioStartSec > 0) {
            $audioInputArgs = sprintf("-ss %.6f -t %.6f", $audioStartSec, $audioDurationSec);
        }

        // Build cmd string with properly quoted paths
        $cmd = sprintf(
            '"%s" -y -f concat -safe 0 -i "%s" %s -i "%s" %s -c:v libx264 -pix_fmt yuv420p -vf fps=fps=30 -c:a aac -shortest "%s"',
            $ffmpeg,
            $concatFilePath,
            $audioInputArgs,
            $audioPath,
            $durationArgs,
            $outputPath
        );

        Log::info("[VideoComposer] Executing FFmpeg command", ['cmd' => $cmd]);

        $output = [];
        $resultCode = -1;
        exec($cmd . ' 2>&1', $output, $resultCode);

        // Clean up concat temp file
        if (file_exists($concatFilePath)) {
            unlink($concatFilePath);
        }

        if ($resultCode !== 0) {
            $errorOutput = implode("\n", $output);
            Log::error("[VideoComposer] FFmpeg composition failed", [
                'result_code' => $resultCode,
                'output' => $errorOutput,
            ]);
            throw new RuntimeException("FFmpeg video composition failed. Exit code {$resultCode}. Output:\n{$errorOutput}");
        }

        Log::info("[VideoComposer] Successfully composed video", ['output_path' => $outputPath]);
    }
}

/**
 * Escape arguments on Windows CLI
 */
if (!function_exists('esc_args')) {
    function esc_args(string $arg): string
    {
        return str_replace('"', '""', $arg);
    }
}
