<?php

$url = 'https://api.quran.com/api/v4/resources/chapter_reciters?language=ar';
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n"
    ]
];
$context = stream_context_create($opts);
$response = @file_get_contents($url, false, $context);

if ($response) {
    $data = json_decode($response, true);
    echo "Success! Reciters count: " . count($data['reciters'] ?? []) . "\n";
    if (isset($data['reciters'])) {
        foreach (array_slice($data['reciters'], 0, 10) as $r) {
            echo "ID: {$r['id']} | Name: {$r['name']} | Translated: " . ($r['translated_name']['name'] ?? '') . "\n";
        }
    }
} else {
    echo "Failed to fetch. Status code or error.\n";
    // Print the last error
    print_r(error_get_last());
}
