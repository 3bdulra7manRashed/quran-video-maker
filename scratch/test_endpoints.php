<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "1. Testing /quran/tafsirs/16?chapter_number=1 ...\n";
$res1 = Http::withoutVerifying()->get('https://api.quran.com/api/v4/quran/tafsirs/16?chapter_number=1')->json();
echo "Res 1 count: " . count($res1['tafsirs'] ?? []) . "\n";
if (!empty($res1['tafsirs'])) {
    echo "Res 1 sample: " . json_encode($res1['tafsirs'][0], JSON_UNESCAPED_UNICODE) . "\n";
}

echo "2. Testing /tafsirs/16/by_ayah/1:1 ...\n";
$res2 = Http::withoutVerifying()->get('https://api.quran.com/api/v4/tafsirs/16/by_ayah/1:1')->json();
echo "Res 2 keys: " . implode(', ', array_keys($res2)) . "\n";
if (!empty($res2['tafsir'])) {
    echo "Res 2 sample: " . json_encode($res2['tafsir'], JSON_UNESCAPED_UNICODE) . "\n";
}

echo "3. Testing /resources/tafsirs ...\n";
$res3 = Http::withoutVerifying()->get('https://api.quran.com/api/v4/resources/tafsirs')->json();
if (!empty($res3['tafsirs'])) {
    foreach ($res3['tafsirs'] as $t) {
        if ($t['id'] == 16 || str_contains($t['slug'] ?? '', 'muyassar')) {
            echo "Resource 16 info: " . json_encode($t, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
