<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "Checking Quran.com API for Reciter ID 97 (Yasser Al-Dosari)...\n\n";

// Test Surah 1 (Al-Fatiha)
$url1 = "https://api.quran.com/api/v4/chapter_recitations/97/1?segments=true";
$res1 = Http::withoutVerifying()->timeout(10)->get($url1);

echo "Surah 1 response status: " . $res1->status() . "\n";
if ($res1->successful()) {
    $data1 = $res1->json();
    $audioUrl = $data1['audio_file']['audio_url'] ?? '';
    $timestamps = $data1['audio_file']['timestamps'] ?? [];
    echo "Audio URL: {$audioUrl}\n";
    echo "Total timestamps count: " . count($timestamps) . "\n";
    if (!empty($timestamps)) {
        echo "Ayah 1:1 segments: " . json_encode($timestamps[0]['segments'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// Test Surah 67 (Al-Mulk)
$url67 = "https://api.quran.com/api/v4/chapter_recitations/97/67?segments=true";
$res67 = Http::withoutVerifying()->timeout(10)->get($url67);
echo "\nSurah 67 (Al-Mulk) response status: " . $res67->status() . "\n";
if ($res67->successful()) {
    $data67 = $res67->json();
    $timestamps67 = $data67['audio_file']['timestamps'] ?? [];
    echo "Total timestamps count: " . count($timestamps67) . "\n";
    if (!empty($timestamps67)) {
        echo "Ayah 67:1 segments count: " . count($timestamps67[0]['segments'] ?? []) . "\n";
        echo "Sample segments: " . json_encode(array_slice($timestamps67[0]['segments'] ?? [], 0, 4), JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// Test Surah 55 (Ar-Rahman)
$url55 = "https://api.quran.com/api/v4/chapter_recitations/97/55?segments=true";
$res55 = Http::withoutVerifying()->timeout(10)->get($url55);
echo "\nSurah 55 (Ar-Rahman) response status: " . $res55->status() . "\n";
if ($res55->successful()) {
    $data55 = $res55->json();
    $timestamps55 = $data55['audio_file']['timestamps'] ?? [];
    echo "Total timestamps count: " . count($timestamps55) . "\n";
    if (!empty($timestamps55)) {
        echo "Ayah 55:1 segments count: " . count($timestamps55[0]['segments'] ?? []) . "\n";
        echo "Sample segments: " . json_encode(array_slice($timestamps55[0]['segments'] ?? [], 0, 4), JSON_UNESCAPED_UNICODE) . "\n";
    }
}
