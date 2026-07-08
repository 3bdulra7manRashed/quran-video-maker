<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$supportedIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 97, 161, 168, 170, 172, 173, 174];

foreach ($supportedIds as $id) {
    $url = "https://api.quran.com/api/v4/chapter_recitations/{$id}/78";
    $response = Http::get($url);
    if ($response->successful()) {
        $audioUrl = $response->json()['audio_file']['audio_url'] ?? null;
        if ($audioUrl) {
            echo "ID {$id}: SUCCESS - {$audioUrl}\n";
        } else {
            echo "ID {$id}: FAILED - No audio_url in JSON response\n";
        }
    } else {
        echo "ID {$id}: FAILED - HTTP status " . $response->status() . "\n";
    }
}
