<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$url = "https://api.quran.com/api/v4/pages/lookup?chapter_number=6&mushaf=1";
echo "Fetching pages lookup: {$url}...\n";
$response = Http::timeout(30)->get($url);
if ($response->successful()) {
    echo "Response:\n";
    print_r($response->json());
} else {
    echo "Request failed with status: " . $response->status() . "\n";
}
