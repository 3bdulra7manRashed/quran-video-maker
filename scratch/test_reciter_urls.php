<?php

$ids = [24, 69, 158];

foreach ($ids as $id) {
    $url = "https://api.quran.com/api/v4/chapter_recitations/{$id}/56?segments=true";
    $response = @file_get_contents($url);
    if (!$response) {
        echo "ID: {$id} | Failed to fetch\n";
        continue;
    }
    
    $data = json_decode($response, true);
    $audioUrl = $data['audio_file']['audio_url'] ?? '';
    
    // Check segments count
    $timestamps = $data['audio_file']['timestamps'] ?? [];
    $segmentedCount = 0;
    foreach ($timestamps as $ts) {
        if (!empty($ts['segments'])) {
            $segmentedCount++;
        }
    }
    
    echo "ID: {$id} | Audio URL: {$audioUrl} | Segmented Verses: {$segmentedCount} / " . count($timestamps) . "\n";
}
