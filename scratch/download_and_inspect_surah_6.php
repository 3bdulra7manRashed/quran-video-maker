<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$surahNumber = 6;
$destFile = __DIR__ . "/surah_6_raw_downloaded.json";

if (!file_exists($destFile)) {
    echo "Downloading raw Surah 6 data from Quran.com...\n";
    $allVerses = [];
    $page = 1;
    $perPage = 50;

    while (true) {
        $url = "https://api.quran.com/api/v4/verses/by_chapter/{$surahNumber}?language=en&words=true&word_fields=code_v1,text_uthmani,text_imlaei&fields=verse_key,verse_number,page_number,juz_number,hizb_number,rub_el_hizb_number,ruku_number,manzil_number,sajdah_number&per_page={$perPage}&page={$page}";
        echo "Fetching page {$page}...\n";
        $response = Http::timeout(30)->get($url);
        if ($response->failed()) {
            die("Failed to fetch page {$page}\n");
        }

        $data = $response->json();
        $allVerses = array_merge($allVerses, $data['verses']);

        $totalRecords = $data['pagination']['total_records'] ?? count($allVerses);
        if (count($allVerses) >= $totalRecords) {
            break;
        }
        $page++;
    }

    file_put_contents($destFile, json_encode($allVerses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Saved raw data to {$destFile}\n";
} else {
    echo "Loading cached raw data from {$destFile}\n";
    $allVerses = json_decode(file_get_contents($destFile), true);
}

echo "Total verses: " . count($allVerses) . "\n";

// Now, let's analyze page 145, verses 132-137.
// Let's filter the verses that belong to page 145 or have verse numbers 132-137.
echo "\n--- Verses 132 to 137 in downloaded data ---\n";
foreach ($allVerses as $verse) {
    $vNum = $verse['verse_number'];
    if ($vNum >= 131 && $vNum <= 138) {
        $vPage = $verse['page_number'];
        echo "Verse {$vNum}: Key={$verse['verse_key']}, Page={$vPage}, Word Count=" . count($verse['words']) . "\n";
        foreach ($verse['words'] as $w) {
            $wPos = $w['position'];
            $wPage = $w['page_number'] ?? 'null';
            $wLine = $w['line_number'] ?? 'null';
            $wGlyph = $w['code_v1'] ?? 'null';
            $wText = $w['text_uthmani'] ?? 'null';
            echo "  Word {$wPos}: Page={$wPage}, Line={$wLine}, Glyph='{$wGlyph}', Text='{$wText}', Type='{$w['char_type_name']}'\n";
        }
    }
}
