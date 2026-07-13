<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Modules\Dataset\Validation\GlyphDatasetValidator;

function fetchRawSurah(int $surahNumber): array
{
    $destFile = __DIR__ . "/surah_{$surahNumber}_raw_downloaded.json";
    if (file_exists($destFile)) {
        return json_decode(file_get_contents($destFile), true);
    }

    echo "Downloading raw Surah {$surahNumber} data from Quran.com...\n";
    $allVerses = [];
    $page = 1;
    $perPage = 50;

    while (true) {
        $url = "https://api.quran.com/api/v4/verses/by_chapter/{$surahNumber}?language=en&words=true&word_fields=code_v1,text_uthmani,text_imlaei&fields=verse_key,verse_number,page_number,juz_number,hizb_number,rub_el_hizb_number,ruku_number,manzil_number,sajdah_number&per_page={$perPage}&page={$page}";
        $response = Http::timeout(30)->get($url);
        if ($response->failed()) {
            die("Failed to fetch Surah {$surahNumber} page {$page}\n");
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
    return $allVerses;
}

function normalizeAutoPageWrap(array &$dataset): array
{
    if (empty($dataset['verses'])) {
        return [];
    }

    // Collect all words into a flat list
    $flatWords = [];
    $wordRefs = [];
    foreach ($dataset['verses'] as $vIdx => &$verse) {
        if (empty($verse['words'])) {
            continue;
        }
        foreach ($verse['words'] as $wIdx => &$word) {
            $flatWords[] = [
                'verse_index' => $vIdx,
                'word_index' => $wIdx,
                'line_number' => isset($word['line_number']) ? (int)$word['line_number'] : null,
                'page_number' => isset($word['page_number']) ? (int)$word['page_number'] : null,
            ];
            // store references to edit them later
            $wordRefs[] = [
                'verse' => &$verse,
                'word' => &$word
            ];
        }
    }
    unset($verse, $word);

    if (empty($flatWords)) {
        return [];
    }

    // Determine initial anchor page
    $currentPage = $flatWords[0]['page_number'];
    if ($currentPage === null) {
        return [];
    }

    $corrections = [];
    $lastLine = $flatWords[0]['line_number'];

    // Loop through and update page numbers when line number decreases
    foreach ($flatWords as $idx => $fw) {
        $l = $fw['line_number'];
        
        if ($l !== null && $lastLine !== null && $l < $lastLine) {
            // Line decreased -> page wrapped!
            $currentPage++;
        }

        // If current page calculated differs from the word's page, record correction
        if ($fw['page_number'] !== $currentPage) {
            $origPage = $fw['page_number'];
            $vNum = $wordRefs[$idx]['verse']['verse_number'];
            $wPos = $wordRefs[$idx]['word']['position'];

            // Update word page
            $wordRefs[$idx]['word']['page_number'] = $currentPage;
            
            // Update parent verse page (we will unify verse page to match its first word page later, or do it on the fly)
            $wordRefs[$idx]['verse']['page_number'] = $currentPage;

            $corrections[] = [
                'verse' => $vNum,
                'word' => $wPos,
                'old' => $origPage,
                'new' => $currentPage,
            ];
        }

        $lastLine = $l;
    }

    // Ensure verse page numbers are consistent with their words' page numbers
    foreach ($dataset['verses'] as &$verse) {
        if (!empty($verse['words'])) {
            // Set verse page to the page of its first word
            $verse['page_number'] = $verse['words'][0]['page_number'];
        }
    }

    return $corrections;
}

$surahsToTest = [6, 55, 79];
$validator = new GlyphDatasetValidator();

foreach ($surahsToTest as $surah) {
    echo "\n--- Testing Surah {$surah} ---\n";
    $rawVerses = fetchRawSurah($surah);
    $dataset = ['verses' => $rawVerses];

    // Validate raw
    $reportRaw = $validator->validate($surah, $dataset);
    echo "Raw Validation: " . ($reportRaw->isValid() ? "PASS" : "FAIL") . "\n";
    if (!$reportRaw->isValid()) {
        echo "Raw Issues Count: " . count($reportRaw->getIssues()) . "\n";
    }

    // Normalize using our auto page wrapping rule
    $corrections = normalizeAutoPageWrap($dataset);
    echo "Applied auto-corrections: " . count($corrections) . "\n";
    if (count($corrections) > 0) {
        // print a summary of corrections
        $byVerse = [];
        foreach ($corrections as $c) {
            $byVerse[$c['verse']][] = $c['word'];
        }
        foreach ($byVerse as $v => $words) {
            echo "  Verse {$v}: corrected page for words " . implode(',', $words) . "\n";
        }
    }

    // Validate corrected
    $reportCorrect = $validator->validate($surah, $dataset);
    echo "Normalized Validation: " . ($reportCorrect->isValid() ? "PASS" : "FAIL") . "\n";
    if (!$reportCorrect->isValid()) {
        echo "Issues remaining:\n";
        foreach ($reportCorrect->getIssues() as $issue) {
            echo "  - " . $issue->message . "\n";
        }
    }
}
