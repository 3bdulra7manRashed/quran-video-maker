<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "Fetching https://api.quran.com/api/v4/quran/tafsirs/16 ...\n";
$response = Http::withoutVerifying()
    ->timeout(60)
    ->get('https://api.quran.com/api/v4/quran/tafsirs/16');

if ($response->failed()) {
    echo "FAILED with status: " . $response->status() . "\n";
    exit(1);
}

$data = $response->json();
echo "Top-level keys: " . implode(', ', array_keys($data)) . "\n";

$tafsirs = $data['tafsirs'] ?? [];
echo "Count of tafsirs: " . count($tafsirs) . "\n";

if (!empty($tafsirs)) {
    echo "Sample #0: " . json_encode($tafsirs[0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "Sample #1: " . json_encode($tafsirs[1], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
