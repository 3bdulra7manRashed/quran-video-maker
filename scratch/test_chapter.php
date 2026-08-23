<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "Testing /tafsirs/16/by_chapter/1 ...\n";
$res = Http::withoutVerifying()->get('https://api.quran.com/api/v4/tafsirs/16/by_chapter/1')->json();
echo "Keys: " . implode(', ', array_keys($res)) . "\n";
$tafsirs = $res['tafsirs'] ?? [];
echo "Count of tafsirs for chapter 1: " . count($tafsirs) . "\n";
if (!empty($tafsirs)) {
    echo "Tafsir 0: " . json_encode($tafsirs[0], JSON_UNESCAPED_UNICODE) . "\n";
    echo "Tafsir 1: " . json_encode($tafsirs[1], JSON_UNESCAPED_UNICODE) . "\n";
}
