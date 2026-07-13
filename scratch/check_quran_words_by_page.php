<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "Fetching page 144 with words and glyphs...\n";
$url = "https://api.quran.com/api/v4/verses/by_page/144?language=en&words=true&word_fields=code_v1,text_uthmani,text_imlaei&fields=verse_key,verse_number,page_number,juz_number,hizb_number,rub_el_hizb_number,ruku_number,manzil_number,sajdah_number";
$response = Http::timeout(30)->get($url);

if ($response->successful()) {
    $data = $response->json();
    foreach ($data['verses'] as $v) {
        if ($v['verse_key'] === '6:131') {
            echo "Verse 6:131 found on page 144 response!\n";
            echo "Verse page_number: {$v['page_number']}\n";
            echo "First word page_number: {$v['words'][0]['page_number']}\n";
        }
    }
} else {
    echo "Failed to fetch page 144\n";
}
