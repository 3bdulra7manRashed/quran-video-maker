<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$surah = 6;
$versesRange = range(131, 137);

echo "===================================================================================\n";
echo "1. QUERYING BY_CHAPTER ENDPOINT\n";
echo "===================================================================================\n";

$byChapterData = [];
$page = 3; // Verses 101 to 150
$url = "https://api.quran.com/api/v4/verses/by_chapter/{$surah}?language=en&words=true&word_fields=code_v1,text_uthmani,text_imlaei&fields=verse_key,verse_number,page_number,juz_number,hizb_number,rub_el_hizb_number,ruku_number,manzil_number,sajdah_number&per_page=50&page={$page}";

$response = Http::timeout(30)->get($url);
if ($response->failed()) {
    die("Failed to fetch by_chapter endpoint\n");
}
$data = $response->json();
foreach ($data['verses'] as $v) {
    $vNum = $v['verse_number'];
    if (in_array($vNum, $versesRange)) {
        $byChapterData[$v['verse_key']] = $v;
    }
}

foreach ($byChapterData as $key => $v) {
    echo "Verse {$key}: page_number = {$v['page_number']}\n";
    foreach ($v['words'] as $w) {
        if ($w['char_type_name'] === 'end') {
            echo "  [END] Word {$w['position']}: page = {$w['page_number']}, line = {$w['line_number']}, glyph = '{$w['code_v1']}'\n";
        } else {
            echo "  Word {$w['position']}: page = {$w['page_number']}, line = {$w['line_number']}, glyph = '{$w['code_v1']}'\n";
        }
    }
}

echo "\n===================================================================================\n";
echo "2. QUERYING BY_PAGE ENDPOINT\n";
echo "===================================================================================\n";

$byPageData = [];
// Verses 131 to 137 can cross pages 144 and 145. Let's fetch both page 144 and 145.
$pages = [144, 145];
foreach ($pages as $p) {
    $url = "https://api.quran.com/api/v4/verses/by_page/{$p}?language=en&words=true&word_fields=code_v1,text_uthmani,text_imlaei&fields=verse_key,verse_number,page_number,juz_number,hizb_number,rub_el_hizb_number,ruku_number,manzil_number,sajdah_number";
    $response = Http::timeout(30)->get($url);
    if ($response->failed()) {
        die("Failed to fetch by_page endpoint for page {$p}\n");
    }
    $data = $response->json();
    foreach ($data['verses'] as $v) {
        $vKey = $v['verse_key'];
        // Split key to verify Surah and Verse number
        $parts = explode(':', $vKey);
        if ((int)$parts[0] === $surah && in_array((int)$parts[1], $versesRange)) {
            // Keep track of which query page returned it
            $v['returned_in_page'] = $p;
            $byPageData[$vKey] = $v;
        }
    }
}

ksort($byPageData);

foreach ($byPageData as $key => $v) {
    echo "Verse {$key} (Returned in Page {$v['returned_in_page']}): page_number field = {$v['page_number']}\n";
    foreach ($v['words'] as $w) {
        if ($w['char_type_name'] === 'end') {
            echo "  [END] Word {$w['position']}: page = {$w['page_number']}, line = {$w['line_number']}, glyph = '{$w['code_v1']}'\n";
        } else {
            echo "  Word {$w['position']}: page = {$w['page_number']}, line = {$w['line_number']}, glyph = '{$w['code_v1']}'\n";
        }
    }
}
