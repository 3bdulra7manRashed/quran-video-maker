<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "Checking all 114 Surahs for Yasser Al-Dosari (ID 97) on Quran.com...\n";

$availableSurahs = [];
$missingSurahs = [];

for ($surah = 1; $surah <= 114; $surah++) {
    $url = "https://api.quran.com/api/v4/chapter_recitations/97/{$surah}?segments=true";
    $res = Http::withoutVerifying()->timeout(5)->get($url);
    if ($res->successful()) {
        $ts = $res->json('audio_file.timestamps') ?? [];
        $hasSegments = false;
        foreach ($ts as $t) {
            if (!empty($t['segments'])) {
                $hasSegments = true;
                break;
            }
        }
        if ($hasSegments) {
            $availableSurahs[] = $surah;
        } else {
            $missingSurahs[] = $surah;
        }
    } else {
        $missingSurahs[] = $surah;
    }
}

echo "Available Surahs with Word Timings for Yasser Al-Dosari: " . count($availableSurahs) . " / 114\n";
echo "Surah numbers: " . implode(', ', $availableSurahs) . "\n\n";

if (!empty($missingSurahs)) {
    echo "Missing Surahs: " . implode(', ', $missingSurahs) . "\n";
}
