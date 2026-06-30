<?php

namespace App\Http\Controllers\Api;

use App\Modules\Quran\Models\AudioFile;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class DatasetAudioController
{
    /**
     * POST /api/datasets/audio
     */
    public function upload(Request $request, QuranPathResolver $pathResolver): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reciter' => 'required|string|exists:reciters,slug',
            'surah'   => 'required|integer|exists:surahs,number',
            'file'    => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $reciterSlug = $request->input('reciter');
        $surahNumber = (int) $request->input('surah');
        $file = $request->file('file');

        // Check if file is valid and extension is mp3
        if ($file->getClientOriginalExtension() !== 'mp3' && $file->getMimeType() !== 'audio/mpeg') {
            return response()->json([
                'error' => 'The uploaded file must be an MP3 audio file.',
            ], 422);
        }

        $reciter = Reciter::where('slug', $reciterSlug)->first();
        if (!$reciter) {
            return response()->json([
                'error' => "Reciter '{$reciterSlug}' not found.",
            ], 404);
        }

        $filename = sprintf('%03d.mp3', $surahNumber);
        $destFile = $pathResolver->audio("{$reciter->slug}/{$filename}");

        // Move/save the uploaded file
        try {
            $file->move(dirname($destFile), $filename);
        } catch (\Throwable $e) {
            Log::error("[DatasetAudioController] Failed to save uploaded audio file: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to save the uploaded audio file on server.',
            ], 500);
        }

        // Probe duration via ffprobe
        $durationMs = 0;
        $format = 'mp3';
        try {
            $probed = $this->probeAudioFile($destFile);
            $durationMs = $probed['duration_ms'];
            $format = $probed['format'];
        } catch (\Throwable $e) {
            Log::warning("[DatasetAudioController] ffprobe failed: " . $e->getMessage() . ". Using fallback duration.");
            $durationMs = 450000; // default fallback (7.5 mins)
        }

        // Create or update AudioFile record
        AudioFile::updateOrCreate([
            'reciter_id'   => $reciter->id,
            'surah_number' => $surahNumber,
        ], [
            'file_path'   => "{$reciter->slug}/{$filename}",
            'duration_ms' => $durationMs,
        ]);

        // Regenerate metadata.json
        $this->updateReciterMetadataFile($reciter, $pathResolver);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Run ffprobe to resolve duration and format.
     */
    private function probeAudioFile(string $filePath): array
    {
        $ffprobe = config('ffmpeg.ffprobe_path', 'ffprobe');
        
        $cmd = sprintf(
            '"%s" -v error -show_entries format=duration,format_name -of default=noprint_wrappers=1:nokey=1 "%s"',
            $ffprobe,
            $filePath
        );

        $output = [];
        $resultCode = -1;
        exec($cmd . ' 2>&1', $output, $resultCode);

        if ($resultCode !== 0 || count($output) < 2) {
            throw new RuntimeException("ffprobe failed. Exit code {$resultCode}.");
        }

        $format = trim($output[0]);
        $durationSeconds = (float) trim($output[1]);
        $durationMs = (int) round($durationSeconds * 1000);

        return [
            'duration_ms' => $durationMs,
            'format'      => $format,
        ];
    }

    /**
     * Regenerate metadata.json for the reciter.
     */
    private function updateReciterMetadataFile(Reciter $reciter, QuranPathResolver $pathResolver): void
    {
        $reciterDir = dirname($pathResolver->audio("{$reciter->slug}/001.mp3"));
        $audioFiles = AudioFile::where('reciter_id', $reciter->id)->get();
        
        $audioFilesMetadata = [];
        foreach ($audioFiles as $af) {
            $audioFilesMetadata[] = [
                'surah_number' => $af->surah_number,
                'file_path'    => $af->file_path,
                'duration_ms'  => $af->duration_ms,
            ];
        }

        $metadata = [
            'reciter' => [
                'name_arabic'  => $reciter->name_arabic,
                'name_english' => $reciter->name_english,
                'slug'         => $reciter->slug,
                'is_default'   => (bool) $reciter->is_default,
            ],
            'audio_files' => $audioFilesMetadata,
        ];

        file_put_contents($reciterDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
