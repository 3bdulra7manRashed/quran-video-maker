<?php

namespace App\Modules\Rendering\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class VideoComposer
{
    /**
     * Compose a sequence of frame images with durations and an audio track into an MP4 video using FFmpeg.
     *
     * @param array $frames Array of arrays containing 'image_path' and 'duration'.
     * @param string $audioPath Absolute path to the audio MP3.
     * @param string $outputPath Absolute path to the output MP4.
     * @param float|null $totalDuration Total duration in seconds to constrain video encoding.
     * @return void
     */
    public function compose(array $frames, string $audioPath, string $outputPath, ?float $totalDuration = null): void
    {
        Log::info("[VideoComposer] Starting video composition", [
            'frames_count' => count($frames),
            'audio' => $audioPath,
            'output' => $outputPath,
            'duration_limit' => $totalDuration,
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
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

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
        Log::info("[VideoComposer] Created FFmpeg concat file", ['path' => $concatFilePath]);

        $ffmpeg = config('ffmpeg.ffmpeg_path', 'ffmpeg');

        // 2. Construct the FFmpeg command
        // Concat demuxer uses: -f concat -safe 0
        if ($totalDuration !== null) {
            $cmd = sprintf(
                '%s -y -f concat -safe 0 -i %s -i %s -vf fps=fps=30 -c:v libx264 -tune stillimage -pix_fmt yuv420p -c:a aac -t %f %s 2>&1',
                escapeshellarg($ffmpeg),
                escapeshellarg($concatFilePath),
                escapeshellarg($audioPath),
                $totalDuration,
                escapeshellarg($outputPath)
            );
        } else {
            $cmd = sprintf(
                '%s -y -f concat -safe 0 -i %s -i %s -vf fps=fps=30 -c:v libx264 -tune stillimage -pix_fmt yuv420p -c:a aac -shortest %s 2>&1',
                escapeshellarg($ffmpeg),
                escapeshellarg($concatFilePath),
                escapeshellarg($audioPath),
                escapeshellarg($outputPath)
            );
        }

        Log::info("[VideoComposer] Running FFmpeg command", ['command' => $cmd]);

        $output = [];
        $resultCode = null;
        exec($cmd, $output, $resultCode);

        // Cleanup temporary concat file
        if (file_exists($concatFilePath)) {
            unlink($concatFilePath);
        }

        if ($resultCode !== 0) {
            $outputStr = implode("\n", $output);
            Log::error("[VideoComposer] FFmpeg execution failed", [
                'result_code' => $resultCode,
                'output' => $outputStr,
            ]);
            throw new RuntimeException("FFmpeg failed with exit code {$resultCode}. Output:\n{$outputStr}");
        }

        Log::info("[VideoComposer] Video successfully generated at {$outputPath}");
    }
}
