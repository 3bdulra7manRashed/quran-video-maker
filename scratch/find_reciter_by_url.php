<?php

for ($id = 1; $id <= 200; $id++) {
    $url = "https://api.quran.com/api/v4/chapter_recitations/{$id}/1";
    $opts = [
        'http' => [
            'timeout' => 3,
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        $audioUrl = $data['audio_file']['audio_url'] ?? '';
        if ($audioUrl && (stripos($audioUrl, 'jaabir') !== false || stripos($audioUrl, 'jaber') !== false)) {
            echo "ID: {$id} | Audio URL: {$audioUrl}\n";
        }
    }
}
echo "Done scanning.\n";
