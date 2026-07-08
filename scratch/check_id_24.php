<?php

echo "Checking reciter ID 24 (Abdullah Ali Jaber) segments across all Surahs...\n";

for ($surah = 1; $surah <= 114; $surah++) {
    $apiUrl = "https://api.quran.com/api/v4/chapter_recitations/24/{$surah}?segments=true";
    
    $opts = [
        'http' => [
            'timeout' => 3,
        ]
    ];
    $context = stream_context_create($opts);
    
    $res = @file_get_contents($apiUrl, false, $context);
    if (!$res) {
        continue;
    }
    
    $json = json_decode($res, true);
    $timestamps = $json['audio_file']['timestamps'] ?? [];
    if (empty($timestamps)) {
        continue;
    }
    
    $hasSegments = false;
    foreach ($timestamps as $ts) {
        if (!empty($ts['segments'])) {
            $hasSegments = true;
            break;
        }
    }
    
    if ($hasSegments) {
        echo "Surah {$surah}: HAS SEGMENTS!\n";
    }
}
echo "Done checking ID 24.\n";
