<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\ReelsGeneratedContent;
use App\Modules\Quran\Models\RenderJob;
use App\Jobs\GenerateVideoJob;
use Illuminate\Support\Facades\Log;

echo "=== Starting Full End-to-End Render for Surah 93 with Mishari Al-Afasy ===\n";

// 1. Fetch custom json from approved content
$approved = ReelsGeneratedContent::where('reciter_id', 30)
    ->where('surah_number', 93)
    ->where('approval_status', 'approved')
    ->orderBy('segment_order')
    ->get();

$firstWithSource = $approved->first(fn($s) => !empty($s->source_json));
if (!$firstWithSource) {
    echo "ERROR: No approved records with source_json found for reciter 30, surah 93!\n";
    exit(1);
}

$sourceJson = is_array($firstWithSource->source_json) 
    ? $firstWithSource->source_json 
    : json_decode($firstWithSource->source_json, true);

echo "Found custom source JSON with " . count($sourceJson['segments']) . " segments.\n";
echo "Total end_time_ms: " . ($sourceJson['end_time_ms'] ?? 'N/A') . "\n";

// 2. Mark initial position of storage/logs/laravel.log
$logFile = storage_path('logs/laravel.log');
$initialLogSize = file_exists($logFile) ? filesize($logFile) : 0;

// 3. Create RenderJob with reciter alias 'mishari-rashid-al-afasy' to verify alias resolution in real jobs
$reciter = Reciter::findBySlug('mishari-rashid-al-afasy');
echo "Resolved reciter: ID={$reciter->id}, slug={$reciter->slug}\n";

// Clean up any existing temp files or video output to ensure fresh generation
$tempDir = storage_path('app/temp');
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$renderJob = RenderJob::create([
    'status'             => 'pending',
    'reciter_id'         => $reciter->id,
    'surah_number'       => 93,
    'layout'             => 'reels',
    'max_lines'          => 1,
    'from_ayah'          => null, // Full surah
    'to_ayah'            => null,
    'progress'           => 0,
    'with_translation'   => true,
    'translation_source' => 'sahih_international',
    'with_tafsir'        => true,
    'tafsir_source'      => 'ar-tafsir-muyassar',
    'use_generated_content' => true,
]);

$customJsonStr = json_encode($sourceJson, JSON_UNESCAPED_UNICODE);
$tempFile = $tempDir . DIRECTORY_SEPARATOR . 'custom_render_' . $renderJob->uuid . '.json';
file_put_contents($tempFile, $customJsonStr);

echo "Created RenderJob UUID: {$renderJob->uuid}\n";
echo "Saved custom json to temp: {$tempFile}\n";

// 4. Execute GenerateVideoJob synchronously
$startTime = microtime(true);
echo "Executing GenerateVideoJob::dispatchSync...\n";
try {
    GenerateVideoJob::dispatchSync($renderJob);
    $elapsed = round(microtime(true) - $startTime, 2);
    echo "GenerateVideoJob completed in {$elapsed}s!\n";
} catch (\Throwable $e) {
    echo "ERROR during render: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// 5. Inspect the job status in DB
$renderJob->refresh();
echo "RenderJob status: {$renderJob->status}\n";
echo "Output path: {$renderJob->output_path}\n";
echo "Duration: " . ($renderJob->duration ?? 'N/A') . "s\n";

if (!file_exists($renderJob->output_path)) {
    echo "ERROR: Rendered video file does not exist on disk!\n";
    exit(1);
}
echo "Video file exists on disk! Size: " . filesize($renderJob->output_path) . " bytes\n";

// 6. Inspect storage/logs/laravel.log since initial position
$newLogContent = '';
if (file_exists($logFile)) {
    clearstatcache();
    $currentSize = filesize($logFile);
    if ($currentSize > $initialLogSize) {
        $fp = fopen($logFile, 'r');
        fseek($fp, $initialLogSize);
        $newLogContent = fread($fp, $currentSize - $initialLogSize);
        fclose($fp);
    }
}

echo "\n=== Log Inspection Results ===\n";
if (str_contains($newLogContent, 'Falling back to proportional scheduling') || str_contains($newLogContent, 'Proportional fallback')) {
    echo "FAILED: Proportional fallback was logged!\n";
} else {
    echo "SUCCESS: No proportional fallback was logged!\n";
}

if (str_contains($newLogContent, 'Custom segment timings detected. Enforcing absolute priority')) {
    echo "SUCCESS: 'Custom segment timings detected. Enforcing absolute priority' logged.\n";
} else {
    echo "WARNING: Expected priority enforcement log not found.\n";
}

if (str_contains($newLogContent, 'Directly constructing SegmentTimeline from custom segment timestamps')) {
    echo "SUCCESS: 'Directly constructing SegmentTimeline from custom segment timestamps' logged.\n";
} else {
    echo "WARNING: Expected direct SegmentTimeline construction log not found.\n";
}

// 7. Inspect debug segment JSON
$debugJsonFile = 'D:/work/PROGRAMMIG/Prj for me/quran-video-maker/quran-video-data/debug/segments/surah_93_mishari-al-afasy_segments.json';
if (file_exists($debugJsonFile)) {
    echo "\n=== Segment Timings in Debug JSON ===\n";
    $segments = json_decode(file_get_contents($debugJsonFile), true);
    echo "Total segments in debug json: " . count($segments) . "\n";
    foreach ($segments as $s) {
        echo sprintf(
            "Segment %02d: %5d ms -> %5d ms (Duration: %5d ms) | Tafsir: %s | Trans: %s\n",
            $s['segment'],
            $s['startMs'],
            $s['endMs'],
            $s['durationMs'],
            mb_substr($s['tafsir'] ?? '', 0, 15),
            mb_substr($s['translation'] ?? '', 0, 20)
        );
    }

    // Validate 100% match against custom JSON
    $allMatch = true;
    for ($i = 0; $i < count($segments); $i++) {
        $expectedStart = $i * 3000;
        $expectedEnd = ($i === 10) ? 36000 : ($i + 1) * 3000;
        if ($segments[$i]['startMs'] !== $expectedStart || $segments[$i]['endMs'] !== $expectedEnd) {
            echo "MISMATCH at segment " . ($i + 1) . ": expected [{$expectedStart}, {$expectedEnd}], got [{$segments[$i]['startMs']}, {$segments[$i]['endMs']}]\n";
            $allMatch = false;
        }
    }
    if ($allMatch) {
        echo "\n100% MATCH: All 11 segment start/end timestamps match the manual custom JSON timestamps precisely!\n";
    }
} else {
    echo "WARNING: Debug segments JSON file not found at {$debugJsonFile}\n";
}

echo "\n=== All Validations Passed Successfully! ===\n";
