<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Fetching list of all recitations from Quran.com...\n";
$res = Http::withoutVerifying()->timeout(15)->get('https://api.quran.com/api/v4/resources/recitations?language=ar');
$recitations = $res->json('recitations') ?? [];
echo "Total recitations available in Quran.com: " . count($recitations) . "\n\n";

$withWordTimings = [];
$withoutWordTimings = [];

foreach ($recitations as $r) {
    $id = $r['id'];
    $name = $r['reciter_name'] ?? ($r['translated_name']['name'] ?? 'Unknown');
    $style = $r['style'] ?? '';
    
    // Check chapter 1 (Al-Fatiha) with segments=true
    $check = Http::withoutVerifying()->timeout(8)->get("https://api.quran.com/api/v4/chapter_recitations/{$id}/1?segments=true");
    
    if (!$check->successful()) {
        $withoutWordTimings[] = ['id' => $id, 'name' => $name, 'style' => $style, 'reason' => 'HTTP ' . $check->status()];
        continue;
    }
    
    $data = $check->json();
    $timestamps = $data['audio_file']['timestamps'] ?? [];
    
    $hasSegments = false;
    $sample = [];
    if (!empty($timestamps)) {
        foreach ($timestamps as $ts) {
            if (!empty($ts['segments']) && is_array($ts['segments'])) {
                $hasSegments = true;
                $sample = $ts['segments'];
                break;
            }
        }
    }
    
    if ($hasSegments) {
        $withWordTimings[] = [
            'id' => $id,
            'name' => $name,
            'style' => $style,
            'sample_ayah' => $timestamps[0]['verse_key'] ?? '',
            'sample_segments' => array_slice($sample, 0, 3),
        ];
        echo "  [FOUND] ID: {$id} | {$name} ({$style})\n";
    } else {
        $withoutWordTimings[] = [
            'id' => $id,
            'name' => $name,
            'style' => $style,
            'reason' => 'No segments array',
        ];
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "SUMMARY RESULTS:\n";
echo "Reciters WITH Word-Level Timings: " . count($withWordTimings) . "\n";
echo "Reciters WITHOUT Word-Level Timings: " . count($withoutWordTimings) . "\n";
echo str_repeat('=', 60) . "\n\n";

echo "--- LIST OF ALL RECITERS WITH VERIFIED WORD TIMINGS ---\n";
foreach ($withWordTimings as $item) {
    $sampleJson = json_encode($item['sample_segments'], JSON_UNESCAPED_UNICODE);
    echo "• ID {$item['id']}: {$item['name']} [{$item['style']}] -> Sample segments: {$sampleJson}\n";
}

echo "\n--- SAMPLE OF RECITERS WITHOUT WORD TIMINGS (first 10) ---\n";
foreach (array_slice($withoutWordTimings, 0, 10) as $item) {
    echo "• ID {$item['id']}: {$item['name']} [{$item['style']}] ({$item['reason']})\n";
}

file_put_contents(
    storage_path('qurancom_timings_scan.json'),
    json_encode([
        'scanned_at' => date('Y-m-d H:i:s'),
        'total' => count($recitations),
        'with_word_timings' => $withWordTimings,
        'without_word_timings' => $withoutWordTimings,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "\nFull detailed scan saved to storage/qurancom_timings_scan.json\n";
