<?php

require 'vendor/autoload.php';
use Illuminate\Support\Facades\Http;

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Check chapter_reciters
$res = Http::withoutVerifying()->get('https://api.quran.com/api/v4/resources/chapter_reciters');
$chapterReciters = $res->json('reciters') ?? [];
echo "Total in resources/chapter_reciters: " . count($chapterReciters) . "\n";

// Let's check how many in chapter_recitations have segments
$withSegments = [];
$withoutSegments = [];

foreach ($chapterReciters as $cr) {
    $id = $cr['id'];
    $name = $cr['reciter_name'] ?? $cr['name'] ?? 'Unknown';
    
    // Check chapter 1
    $check = Http::withoutVerifying()->timeout(5)->get("https://api.quran.com/api/v4/chapter_recitations/{$id}/1?segments=true");
    if ($check->successful()) {
        $ts = $check->json('audio_file.timestamps') ?? [];
        $hasSeg = false;
        foreach ($ts as $t) {
            if (!empty($t['segments'])) {
                $hasSeg = true;
                break;
            }
        }
        if ($hasSeg) {
            $withSegments[] = ['id' => $id, 'name' => $name];
            echo "  [SEGMENTS FOUND] ID: {$id} - {$name}\n";
        } else {
            $withoutSegments[] = ['id' => $id, 'name' => $name];
        }
    } else {
        $withoutSegments[] = ['id' => $id, 'name' => $name, 'err' => $check->status()];
    }
}

echo "\nSummary of chapter_reciters:\n";
echo "With segments: " . count($withSegments) . "\n";
echo "Without segments: " . count($withoutSegments) . "\n";
