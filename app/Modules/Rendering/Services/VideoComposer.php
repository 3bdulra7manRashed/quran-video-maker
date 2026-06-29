<?php

namespace App\Modules\Rendering\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class VideoComposer
{
    /**
     * Compose static frame image and audio track into an MP4 video using FFmpeg.
     *
     * @param string $framePath Absolute path to the frame PNG.
     * @param string $audioPath Absolute path to the audio MP3.
     * @param string $outputPath Absolute path to the output MP4.
     * @return void
     */
    public function compose(string $framePath, string $audioPath, string $outputPath): void
    {
        Log::info("[VideoComposer] Starting video composition", [
            'frame' => $framePath,
            'audio' => $audioPath,
            'output' => $outputPath,
        ]);

        if (!file_exists($framePath)) {
            throw new RuntimeException("Frame image not found: {$framePath}");
        }

        if (!file_exists($audioPath)) {
            throw new RuntimeException("Audio file not found: {$audioPath}");
        }

        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $ffmpeg = config('ffmpeg.ffmpeg_path', 'ffmpeg');

        // Construct the FFmpeg command
        // We use -y to overwrite output, and escapeshellarg to avoid shell injection issues.
        $cmd = sprintf(
            '%s -y -loop 1 -i %s -i %s -c:v libx264 -tune stillimage -pix_fmt yuv420p -r 30 -c:a aac -shortest %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($framePath),
            escapeshellarg($audioPath),
            escapeshellarg($outputPath)
        );

        Log::info("[VideoComposer] Running FFmpeg command", ['command' => $cmd]);

        $output = [];
        $resultCode = null;
        exec($cmd, $output, $resultCode);

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
