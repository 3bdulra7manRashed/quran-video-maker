<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "Fetching page 144 verses lookup...\n";
$response144 = Http::timeout(30)->get("https://api.quran.com/api/v4/verses/by_page/144?words=false");
if ($response144->successful()) {
    $data144 = $response144->json();
    echo "Page 144 verses:\n";
    foreach ($data144['verses'] as $v) {
        echo "  - Verse: {$v['verse_key']}\n";
    }
} else {
    echo "Failed to fetch page 144\n";
}

echo "\nFetching page 145 verses lookup...\n";
$response145 = Http::timeout(30)->get("https://api.quran.com/api/v4/verses/by_page/145?words=false");
if ($response145->successful()) {
    $data145 = $response145->json();
    echo "Page 145 verses:\n";
    foreach ($data145['verses'] as $v) {
        echo "  - Verse: {$v['verse_key']}\n";
    }
} else {
    echo "Failed to fetch page 145\n";
}
