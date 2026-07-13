<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Modules\Quran\Models\Surah;
use App\Modules\Dataset\Validation\GlyphDatasetValidator;

$surahNumber = 55;
$surah = Surah::where('number', $surahNumber)->first();
if (!$surah) {
    die("Surah 55 not found in DB\n");
}

$startPage = $surah->start_page;
$endPage = $surah->end_page;

$allVerses = [];
for ($page = $startPage; $page <= $endPage; $page++) {
    $url = "https://api.quran.com/api/v4/verses/by_page/{$page}?language=en&words=true&word_fields=code_v1,text_uthmani,text_imlaei&fields=verse_key,verse_number,page_number,juz_number,hizb_number,rub_el_hizb_number,ruku_number,manzil_number,sajdah_number";
    $response = Http::timeout(30)->get($url);
    if ($response->failed()) {
        die("Failed to fetch page {$page}\n");
    }
    $data = $response->json();
    foreach ($data['verses'] as $verse) {
        if (str_starts_with($verse['verse_key'], "{$surahNumber}:")) {
            $verse['page_number'] = $page;
            if (isset($verse['words']) && is_array($verse['words'])) {
                foreach ($verse['words'] as &$word) {
                    $word['page_number'] = $page;
                }
            }
            $allVerses[] = $verse;
        }
    }
}

usort($allVerses, fn($a, $b) => $a['verse_number'] <=> $b['verse_number']);

$dataset = ['verses' => $allVerses];

// Let's validate this dataset using the validator *without* the normalizer rules to see if it is clean
$validator = new GlyphDatasetValidator();
$report = $validator->validate($surahNumber, $dataset);
echo "Surah 55 page-by-page download validation: " . ($report->isValid() ? "PASS" : "FAIL") . "\n";
if (!$report->isValid()) {
    foreach ($report->getIssues() as $issue) {
        echo "  - " . $issue->message . "\n";
    }
}
