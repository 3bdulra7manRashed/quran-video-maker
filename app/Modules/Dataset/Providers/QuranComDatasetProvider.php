<?php

namespace App\Modules\Dataset\Providers;

use App\Modules\Import\Services\ImportAyahsAndWordsService;
use App\Modules\Import\Services\ImportWordTimingsService;
use App\Modules\Quran\Models\AudioFile;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Word;
use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class QuranComDatasetProvider implements DatasetProvider
{
    protected ImportAyahsAndWordsService $glyphsImporter;
    protected ImportWordTimingsService $timingsImporter;
    protected QuranPathResolver $pathResolver;

    protected array $reciterMapping = [
        'yasser-al-dosari' => [
            'id' => 97,
            'audio_base_url' => 'https://server11.mp3quran.net/yasser/',
        ],
        'ali-jaber' => [
            'id' => 158,
            'audio_base_url' => 'https://download.quranicaudio.com/quran/ali_jaber/',
        ],
    ];

    public function __construct(
        ImportAyahsAndWordsService $glyphsImporter,
        ImportWordTimingsService $timingsImporter,
        QuranPathResolver $pathResolver
    ) {
        $this->glyphsImporter = $glyphsImporter;
        $this->timingsImporter = $timingsImporter;
        $this->pathResolver = $pathResolver;
    }

    /**
     * Determine if glyph data is already downloaded on disk AND imported into database.
     */
    public function determineGlyphsAvailability(int $surahNumber): bool
    {
        $destFile = storage_path("app/quran/glyph/surah_{$surahNumber}.json");
        $fileExists = file_exists($destFile) && filesize($destFile) > 0;
        if (!$fileExists) {
            return false;
        }

        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            return false;
        }

        return Word::whereHas('ayah', fn($q) => $q->where('surah_id', $surah->id))
            ->whereNotNull('glyph_text')
            ->where('glyph_text', '!=', '')
            ->exists();
    }

    /**
     * Download glyph data from Quran.com.
     */
    public function downloadGlyphs(int $surahNumber): void
    {
        Log::info("[QuranComDatasetProvider] Downloading glyphs for Surah {$surahNumber}...");
        
        $surah = Surah::where('number', $surahNumber)->first();
        if (!$surah) {
            throw new RuntimeException("Surah {$surahNumber} not found in database.");
        }

        $startPage = $surah->start_page;
        $endPage = $surah->end_page;
        $allVerses = [];

        for ($page = $startPage; $page <= $endPage; $page++) {
            $url = "https://api.quran.com/api/v4/verses/by_page/{$page}";
            $queryParams = [
                'language' => 'en',
                'words' => 'true',
                'word_fields' => 'code_v1,text_uthmani,text_imlaei',
                'fields' => 'verse_key,verse_number,page_number,juz_number,hizb_number,rub_el_hizb_number,ruku_number,manzil_number,sajdah_number',
            ];

            $response = Http::withoutVerifying()
                ->timeout(30)
                ->retry(3, 200)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url, $queryParams);

            if ($response->failed()) {
                Log::error("Failed to download glyphs from Quran.com for surah {$surahNumber}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                ]);
                throw new RuntimeException("Quran.com API error: " . $response->status() . " - " . $response->body());
            }

            $data = $response->json();
            if (!isset($data['verses'])) {
                throw new RuntimeException("Invalid response format from Quran.com API (missing 'verses' key).");
            }

            foreach ($data['verses'] as $verse) {
                // Filter to keep only verses belonging to the requested Surah
                if (str_starts_with($verse['verse_key'], "{$surahNumber}:")) {
                    // Overwrite page numbers with the authoritative requested page
                    $verse['page_number'] = $page;
                    if (isset($verse['words']) && is_array($verse['words'])) {
                        foreach ($verse['words'] as &$word) {
                            $word['page_number'] = $page;
                        }
                    }
                    $allVerses[] = $verse;
                }
            }
        }

        // Sort the fetched verses by verse_number to guarantee ascending sequence
        usort($allVerses, fn($a, $b) => $a['verse_number'] <=> $b['verse_number']);

        $destDir = storage_path('app/quran/glyph');
        if (!file_exists($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $destFile = $destDir . "/surah_{$surahNumber}.json";

        // Route through Dataset Pipeline
        $pipeline = app(\App\Modules\Dataset\Services\DatasetPipeline::class);
        $dataset = ['verses' => $allVerses];
        
        $metadata = new \App\Modules\Dataset\Validation\DatasetMetadata(
            'quran_com',
            'v4',
            now()->toIso8601String(),
            now()->toIso8601String(),
            'glyphs',
            $surahNumber
        );

        $result = $pipeline->process($surahNumber, $dataset, $metadata);

        file_put_contents($destFile, json_encode($result->dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Write metadata to separate file
        $metaDir = $destDir . '/metadata';
        if (!file_exists($metaDir)) {
            mkdir($metaDir, 0755, true);
        }
        $metaFile = $metaDir . "/surah_{$surahNumber}.metadata.json";
        file_put_contents($metaFile, json_encode($result->metadata->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Import glyph data from disk.
     */
    public function importGlyphs(int $surahNumber): void
    {
        Log::info("[QuranComDatasetProvider] Importing glyphs for Surah {$surahNumber}...");
        $destFile = storage_path("app/quran/glyph/surah_{$surahNumber}.json");
        if (!file_exists($destFile)) {
            throw new RuntimeException("Glyph source file not found for Surah {$surahNumber} at {$destFile}");
        }

        $this->glyphsImporter->import($destFile);
    }

    /**
     * Determine if audio data is already downloaded on disk.
     */
    public function determineAudioAvailability(string $reciterSlug, int $surahNumber): bool
    {
        $filename = sprintf('%03d.mp3', $surahNumber);
        $destFile = $this->pathResolver->audio("{$reciterSlug}/{$filename}");
        return file_exists($destFile) && filesize($destFile) > 0;
    }

    /**
     * Download audio file from the external source.
     */
    public function downloadAudio(string $reciterSlug, int $surahNumber): void
    {
        Log::info("[QuranComDatasetProvider] Downloading audio for {$reciterSlug} Surah {$surahNumber}...");
        $mapping = $this->reciterMapping[$reciterSlug] ?? null;
        if (!$mapping) {
            throw new RuntimeException("No configuration mapping found for reciter '{$reciterSlug}' in QuranComDatasetProvider.");
        }

        $filename = sprintf('%03d.mp3', $surahNumber);
        $url = $mapping['audio_base_url'] . $filename;
        $destFile = $this->pathResolver->audio("{$reciterSlug}/{$filename}");

        $response = Http::withoutVerifying()->timeout(600)->sink($destFile)->get($url);
        if ($response->failed()) {
            if (file_exists($destFile)) {
                unlink($destFile);
            }
            throw new RuntimeException("Failed to download audio file from {$url}");
        }

        // Probe duration using ffprobe
        $ffprobe = config('ffmpeg.ffprobe_path', 'ffprobe');
        $durationMs = 0;

        if (file_exists($destFile) && filesize($destFile) > 0) {
            $cmd = sprintf(
                '"%s" -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "%s"',
                $ffprobe,
                $destFile
            );

            $output = [];
            $resultCode = -1;
            exec($cmd . ' 2>&1', $output, $resultCode);

            if ($resultCode === 0 && !empty($output)) {
                $durationSeconds = (float) trim($output[0]);
                $durationMs = (int) round($durationSeconds * 1000);
            }
        }

        if ($durationMs <= 0) {
            $durationMs = 450000; // default fallback duration
        }

        $reciter = Reciter::where('slug', $reciterSlug)->first();
        if (!$reciter) {
            throw new RuntimeException("Reciter '{$reciterSlug}' not found in database.");
        }

        // Register AudioFile
        AudioFile::updateOrCreate([
            'reciter_id' => $reciter->id,
            'surah_number' => $surahNumber,
        ], [
            'file_path' => "{$reciterSlug}/{$filename}",
            'duration_ms' => $durationMs,
        ]);

        // Regenerate metadata.json
        $this->updateReciterMetadataFile($reciter);
    }

    /**
     * Determine if timing data is already downloaded on disk.
     */
    public function determineTimingsAvailability(string $reciterSlug, int $surahNumber): bool
    {
        $targetDir = storage_path('app/quran/timings');
        if (!is_dir($targetDir)) {
            return false;
        }

        $files = glob($targetDir . "/timings_{$surahNumber}_*.json");
        return !empty($files);
    }

    /**
     * Download timings from Quran.com.
     */
    public function downloadTimings(string $reciterSlug, int $surahNumber): void
    {
        Log::info("[QuranComDatasetProvider] Downloading timings for {$reciterSlug} Surah {$surahNumber}...");
        $mapping = $this->reciterMapping[$reciterSlug] ?? null;
        if (!$mapping) {
            Log::warning("[QuranComDatasetProvider] No configuration mapping found for reciter '{$reciterSlug}'. Timings download skipped.");
            return;
        }

        $quranComReciterId = $mapping['id'] ?? $mapping['quran_com_reciter_id'] ?? null;
        if ($quranComReciterId === null) {
            Log::info("[QuranComDatasetProvider] No Quran.com reciter mapping for '{$reciterSlug}'. Timings download skipped.");
            return;
        }

        $url = "https://api.quran.com/api/v4/chapter_recitations/{$quranComReciterId}/{$surahNumber}?segments=true";
        $response = Http::withoutVerifying()->timeout(30)->get($url);
        if ($response->failed()) {
            Log::warning("[QuranComDatasetProvider] Timings download failed from {$url}.");
            return;
        }

        $data = $response->json();
        if (!isset($data['audio_file']['timestamps'])) {
            Log::info("[QuranComDatasetProvider] No timestamps returned from Quran.com timing API for Surah {$surahNumber}.");
            return;
        }

        $targetDir = storage_path('app/quran/timings');
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach ($data['audio_file']['timestamps'] as $ts) {
            $verseKey = $ts['verse_key'];
            $segments = $ts['segments'] ?? [];

            if (empty($segments)) {
                continue;
            }

            $filteredSegments = array_values(array_filter($segments, function ($seg) {
                return is_array($seg) && count($seg) === 3;
            }));

            if (empty($filteredSegments)) {
                continue;
            }

            $filename = "timings_" . str_replace(':', '_', $verseKey) . ".json";
            $targetPath = $targetDir . '/' . $filename;
            
            file_put_contents($targetPath, json_encode($filteredSegments, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Import timings from disk.
     */
    public function importTimings(string $reciterSlug, int $surahNumber): void
    {
        Log::info("[QuranComDatasetProvider] Importing timings for {$reciterSlug} Surah {$surahNumber}...");
        $reciter = Reciter::where('slug', $reciterSlug)->first();
        if (!$reciter) {
            throw new RuntimeException("Reciter '{$reciterSlug}' not found in database.");
        }

        $targetDir = storage_path('app/quran/timings');
        if (!is_dir($targetDir)) {
            return;
        }

        $files = glob($targetDir . "/timings_{$surahNumber}_*.json");
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            $this->timingsImporter->import($file, $reciter->id);
        }
    }

    protected function updateReciterMetadataFile(Reciter $reciter): void
    {
        $reciterDir = dirname($this->pathResolver->audio("{$reciter->slug}/001.mp3"));
        $audioFiles = AudioFile::where('reciter_id', $reciter->id)->get();
        
        $audioFilesMetadata = [];
        foreach ($audioFiles as $af) {
            $audioFilesMetadata[] = [
                'surah_number' => $af->surah_number,
                'file_path' => $af->file_path,
                'duration_ms' => $af->duration_ms,
            ];
        }

        $metadata = [
            'reciter' => [
                'name_arabic' => $reciter->name_arabic,
                'name_english' => $reciter->name_english,
                'slug' => $reciter->slug,
                'is_default' => (bool) $reciter->is_default,
            ],
            'audio_files' => $audioFilesMetadata,
        ];

        file_put_contents($reciterDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
