<?php

echo "Scanning reciter IDs 1 to 500 for non-empty segments on Surah 56...\n";

for ($id = 1; $id <= 500; $id++) {
    $apiUrl = "https://api.quran.com/api/v4/chapter_recitations/{$id}/56?segments=true";
    
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
        $audioUrl = $json['audio_file']['audio_url'] ?? '';
        echo "ID: {$id} HAS SEGMENTS! Audio URL: {$audioUrl}\n";
    }
}
echo "Scan complete.\n";
