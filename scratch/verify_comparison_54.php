<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Modules\Quran\Models\Surah;

$surahNumber = 54;
$surah = Surah::where('number', $surahNumber)->first();
if (!$surah) {
    die("Surah 54 not found in DB\n");
}

$startPage = $surah->start_page;
$endPage = $surah->end_page;

echo "Surah 54 pages: {$startPage} to {$endPage}\n";

// Proposed page-by-page download method
$allVerses = [];
for ($page = $startPage; $page <= $endPage; $page++) {
    $url = "https://api.quran.com/api/v4/verses/by_page/{$page}?language=en&words=true&word_fields=code_v1,text_uthmani,text_imlaei&fields=verse_key,verse_number,page_number,juz_number,hizb_number,rub_el_hizb_number,ruku_number,manzil_number,sajdah_number";
    echo "Fetching page {$page}...\n";
    $response = Http::timeout(30)->get($url);
    if ($response->failed()) {
        die("Failed to fetch page {$page}\n");
    }
    $data = $response->json();
    foreach ($data['verses'] as $verse) {
        // Filter by Surah
        if (str_starts_with($verse['verse_key'], "{$surahNumber}:")) {
            // Apply page normalization
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

// Sort by verse_number just in case
usort($allVerses, fn($a, $b) => $a['verse_number'] <=> $b['verse_number']);

// Load existing file from disk
$existingFile = storage_path("app/quran/glyph/surah_{$surahNumber}.json");
if (!file_exists($existingFile)) {
    die("Existing file not found at {$existingFile}\n");
}
$existingData = json_decode(file_get_contents($existingFile), true);
$existingVerses = $existingData['verses'] ?? $existingData;

// Compare
if (count($allVerses) !== count($existingVerses)) {
    echo "WARNING: Verse count mismatch! Proposed=" . count($allVerses) . ", Existing=" . count($existingVerses) . "\n";
}

$mismatches = 0;
foreach ($allVerses as $idx => $vNew) {
    $vOld = $existingVerses[$idx] ?? null;
    if (!$vOld) {
        echo "Verse index {$idx} not found in existing data\n";
        $mismatches++;
        continue;
    }

    if ($vNew['verse_key'] !== $vOld['verse_key']) {
        echo "Verse key mismatch at index {$idx}: Proposed={$vNew['verse_key']}, Existing={$vOld['verse_key']}\n";
        $mismatches++;
        continue;
    }

    // Let's strip audio_url or check for exact content
    $vNewWords = $vNew['words'];
    $vOldWords = $vOld['words'];

    if (count($vNewWords) !== count($vOldWords)) {
        echo "Word count mismatch in Verse {$vNew['verse_key']}: Proposed=" . count($vNewWords) . ", Existing=" . count($vOldWords) . "\n";
        $mismatches++;
        continue;
    }

    foreach ($vNewWords as $wIdx => $wNew) {
        $wOld = $vOldWords[$wIdx];
        
        // Compare keys (except audio_url if it's dynamic or something, but let's check)
        foreach ($wNew as $k => $val) {
            if ($k === 'audio_url') {
                // Skip comparison if needed or check it
                continue;
            }
            if (($wOld[$k] ?? null) !== $val) {
                // If it is page_number, let's print it to see if it changed
                echo "Word mismatch in Verse {$vNew['verse_key']} position {$wNew['position']} field '{$k}': Proposed=" . json_encode($val) . ", Existing=" . json_encode($wOld[$k] ?? null) . "\n";
                $mismatches++;
            }
        }
    }
}

if ($mismatches === 0) {
    echo "SUCCESS: Proposed page-by-page dataset matches existing dataset perfectly!\n";
} else {
    echo "FAIL: Found {$mismatches} mismatches\n";
}
