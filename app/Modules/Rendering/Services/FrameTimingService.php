<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Layout\DTO\Frame;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class FrameTimingService
{
    /**
     * Populate startMs and endMs timings for each Frame based on word timing metadata.
     *
     * @param Frame[] $frames
     * @param float $totalAudioDurationSec
     * @return Frame[]
     */
    public function populateTimings(array $frames, float $totalAudioDurationSec): array
    {
        Log::info("[FrameTimingService] Starting frame timing scheduling", [
            'frames_count' => count($frames),
            'audio_duration_sec' => $totalAudioDurationSec,
        ]);

        if (empty($frames)) {
            return [];
        }

        $totalAudioDurationMs = (int) round($totalAudioDurationSec * 1000);

        // 1. Calculate raw timing for each frame from its spoken words
        foreach ($frames as $frame) {
            $startMs = null;
            $endMs = null;

            foreach ($frame->lines as $lineData) {
                foreach ($lineData['words'] as $word) {
                    // Filter: char_type = word only
                    if ($word->char_type !== 'word') {
                        continue;
                    }

                    $wordStart = $word->start_ms_from_surah;
                    $wordEnd = $word->end_ms_from_surah;

                    if ($wordStart === null || $wordEnd === null) {
                        continue;
                    }

                    if ($startMs === null || $wordStart < $startMs) {
                        $startMs = $wordStart;
                    }

                    if ($endMs === null || $wordEnd > $endMs) {
                        $endMs = $wordEnd;
                    }
                }
            }

            // Fallbacks if no spoken words found (highly unlikely for valid pages)
            $frame->startMs = $startMs ?? 0;
            $frame->endMs = $endMs ?? 0;
        }

        // 2. Perform continuous boundary adjustment to prevent timeline gaps/overlaps
        $frameCount = count($frames);

        // First frame must start at 0ms
        $frames[0]->startMs = 0;

        for ($i = 0; $i < $frameCount - 1; $i++) {
            $currentFrame = $frames[$i];
            $nextFrame = $frames[$i + 1];

            // Transition from current to next frame happens at the start of the next frame's first spoken word
            $currentFrame->endMs = $nextFrame->startMs;

            // Validation checks
            if ($currentFrame->endMs <= $currentFrame->startMs) {
                // If a frame has zero or negative duration due to bad timings, fix it by adding 1ms
                $currentFrame->endMs = $currentFrame->startMs + 1;
                $nextFrame->startMs = $currentFrame->endMs;
            }
        }

        // Last frame must run until the end of the audio track
        $lastFrame = $frames[$frameCount - 1];
        $lastFrame->endMs = max($lastFrame->startMs + 1, $totalAudioDurationMs);

        // 3. Validation and Monotonicity assertion
        $previousEndMs = 0;
        foreach ($frames as $index => $frame) {
            $frameNum = $frame->index;

            if ($frame->startMs < 0) {
                throw new InvalidArgumentException(
                    "Validation failed: Frame {$frameNum} has negative startMs ({$frame->startMs})."
                );
            }

            if ($frame->endMs <= $frame->startMs) {
                throw new InvalidArgumentException(
                    "Validation failed: Frame {$frameNum} has endMs ({$frame->endMs}) <= startMs ({$frame->startMs})."
                );
            }

            if ($index > 0 && $frame->startMs < $previousEndMs) {
                throw new InvalidArgumentException(
                    "Validation failed: Frame timeline overlap/overlap check. Frame {$frameNum} startMs ({$frame->startMs}) is less than previous frame endMs ({$previousEndMs})."
                );
            }

            $previousEndMs = $frame->endMs;
        }

        Log::info("[FrameTimingService] Timing scheduling completed successfully and validated.");

        return $frames;
    }
}
