<?php

echo "Scanning reciter IDs for non-empty segments...\n";

// Fetch the 12 reciters to print their names if they match
$url = 'https://api.quran.com/api/v4/resources/recitations?per_page=200';
$response = @file_get_contents($url);
$reciters = [];
if ($response) {
    $data = json_decode($response, true);
    foreach ($data['recitations'] ?? [] as $r) {
        $reciters[$r['id']] = $r;
    }
}

// Also scan IDs from 1 to 200
for ($id = 1; $id <= 200; $id++) {
    $apiUrl = "https://api.quran.com/api/v4/chapter_recitations/{$id}/114?segments=true";
    
    // Set a short timeout
    $opts = [
        'http' => [
            'timeout' => 5,
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
    
    // Check if any timestamps have non-empty segments
    $hasSegments = false;
    foreach ($timestamps as $ts) {
        if (!empty($ts['segments'])) {
            $hasSegments = true;
            break;
        }
    }
    
    if ($hasSegments) {
        $name = '';
        $arName = '';
        if (isset($reciters[$id])) {
            $name = $reciters[$id]['reciter_name'];
            $arName = $reciters[$id]['translated_name']['name'] ?? '';
        } else {
            // Let's fetch the info for this specific ID if we don't have it
            $infoUrl = "https://api.quran.com/api/v4/resources/recitations?language=ar";
            // Wait, we can just print the ID, and we'll check it
        }
        echo "ID: {$id} HAS SEGMENTS! Name in list: EN: {$name} | AR: {$arName}\n";
    }
}
