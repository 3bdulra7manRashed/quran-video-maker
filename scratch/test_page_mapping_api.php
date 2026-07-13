<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Modules\Dataset\Validation\GlyphDatasetValidator;

function getPageMapForSurah(int $startPage, int $endPage): array
{
    $map = [];
    for ($page = $startPage; $page <= $endPage; $page++) {
        echo "Fetching page {$page} mapping...\n";
        $response = Http::timeout(30)->get("https://api.quran.com/api/v4/verses/by_page/{$page}?words=false");
        if (!$response->successful()) {
            die("Failed to fetch mapping for page {$page}\n");
        }
        $data = $response->json();
        foreach ($data['verses'] as $v) {
            $map[$v['verse_key']] = $page;
        }
    }
    return $map;
}

$surahsToTest = [
    6 => ['start' => 128, 'end' => 150],
    55 => ['start' => 531, 'end' => 534],
    79 => ['start' => 583, 'end' => 584],
];

$validator = new GlyphDatasetValidator();

foreach ($surahsToTest as $surah => $pages) {
    echo "\n--- Testing Surah {$surah} with Page Mapping API ---\n";
    
    // Load raw downloaded dataset
    $destFile = __DIR__ . "/surah_{$surah}_raw_downloaded.json";
    if (!file_exists($destFile)) {
        die("Raw file for Surah {$surah} not found. Run test_auto_page_wrapping.php first.\n");
    }
    $allVerses = json_decode(file_get_contents($destFile), true);
    $dataset = ['verses' => $allVerses];

    // Fetch correct page map
    $pageMap = getPageMapForSurah($pages['start'], $pages['end']);

    // Apply correction
    $correctedCount = 0;
    foreach ($dataset['verses'] as &$verse) {
        $vKey = $verse['verse_key'];
        $correctPage = $pageMap[$vKey] ?? null;
        if ($correctPage !== null) {
            if ($verse['page_number'] !== $correctPage) {
                $verse['page_number'] = $correctPage;
                $correctedCount++;
            }
            foreach ($verse['words'] as &$word) {
                if (($word['page_number'] ?? null) !== $correctPage) {
                    $word['page_number'] = $correctPage;
                }
            }
        }
    }
    unset($verse);

    echo "Corrected page numbers for {$correctedCount} verses.\n";

    // Validate
    $report = $validator->validate($surah, $dataset);
    echo "Validation after page mapping correction: " . ($report->isValid() ? "PASS" : "FAIL") . "\n";
    if (!$report->isValid()) {
        echo "Issues remaining:\n";
        foreach ($report->getIssues() as $issue) {
            echo "  - " . $issue->message . "\n";
        }
    }
}
