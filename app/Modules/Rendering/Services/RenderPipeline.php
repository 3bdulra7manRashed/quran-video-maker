<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\AudioFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RenderPipeline
{
    protected FrameRenderer $frameRenderer;
    protected VideoComposer $videoComposer;

    public function __construct(FrameRenderer $frameRenderer, VideoComposer $videoComposer)
    {
        $this->frameRenderer = $frameRenderer;
        $this->videoComposer = $videoComposer;
    }

    /**
     * Run the video rendering pipeline for a given Surah number.
     *
     * @param int $surahNumber
     * @return string Path to the generated video.
     */
    public function render(int $surahNumber): string
    {
        Log::info("[RenderPipeline] Starting pipeline for Surah {$surahNumber}");

        // 1. Select Surah
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new RuntimeException("Surah {$surahNumber} not found in database.");
        }

        // 2. Select default Reciter
        $reciter = Reciter::where('slug', 'yasser-al-dosari')->first();
        if (!$reciter) {
            throw new RuntimeException("Default reciter 'yasser-al-dosari' not found in database.");
        }

        // 3. Load AudioFile metadata
        $audioFile = AudioFile::where('reciter_id', $reciter->id)
            ->where('surah_number', $surahNumber)
            ->first();

        if (!$audioFile) {
            throw new RuntimeException("Audio file metadata not found for Surah {$surahNumber} and reciter '{$reciter->slug}'.");
        }

        $absoluteAudioPath = storage_path('app/quran/audio/' . $audioFile->file_path);
        if (!file_exists($absoluteAudioPath)) {
            throw new RuntimeException("Audio file does not exist on disk: {$absoluteAudioPath}");
        }

        // 4. Resolve paths
        $tempFramePath = storage_path("app/temp/surah_{$surahNumber}_frame.png");
        $outputVideoPath = storage_path("app/output/surah_{$surahNumber}.mp4");

        // 5. Render static frame
        $this->frameRenderer->render($surah, $tempFramePath);

        // 6. Compose video
        $this->videoComposer->compose($tempFramePath, $absoluteAudioPath, $outputVideoPath);

        // Clean up temp frame file
        if (file_exists($tempFramePath)) {
            unlink($tempFramePath);
        }

        Log::info("[RenderPipeline] Completed pipeline for Surah {$surahNumber}");

        return $outputVideoPath;
    }
}
