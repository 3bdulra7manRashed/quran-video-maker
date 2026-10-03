<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$url = "https://api.quran.com/api/v4/chapter_recitations/97/85?segments=true";
$res = Http::withoutVerifying()->timeout(10)->get($url);

if ($res->successful()) {
    $timestamps = $res->json('audio_file.timestamps') ?? [];
    echo "Total timestamps returned from Quran.com for Surah 85: " . count($timestamps) . "\n\n";
    
    foreach ($timestamps as $ts) {
        $verseKey = $ts['verse_key'];
        $segmentsCount = count($ts['segments'] ?? []);
        $durationMs = $ts['duration_ms'] ?? 'null';
        echo "Verse {$verseKey} | Segments: {$segmentsCount} | Duration: {$durationMs}ms\n";
        if ($verseKey === '85:10') {
            echo "  85:10 segments data: " . json_encode($ts['segments'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} else {
    echo "Request failed: " . $res->status() . "\n";
}
