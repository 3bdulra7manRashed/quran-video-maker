<?php

$destFile = __DIR__ . "/surah_6_raw_downloaded.json";
$allVerses = json_decode(file_get_contents($destFile), true);

function dumpSequence($verses) {
    foreach ($verses as $verse) {
        $vNum = $verse['verse_number'];
        if ($vNum >= 131 && $vNum <= 137) {
            foreach ($verse['words'] as $w) {
                $wPos = $w['position'];
                $wPage = $w['page_number'] ?? 'null';
                $wLine = $w['line_number'] ?? 'null';
                $wGlyph = $w['code_v1'] ?? 'null';
                echo sprintf("  - Verse: %3d | Page: %3d | Line: %2d | Word Pos: %2d | Glyph: %s\n", $vNum, $wPage, $wLine, $wPos, $wGlyph);
            }
        }
    }
}

echo "=== BEFORE TRANSFORMATION (Raw Data) ===\n";
dumpSequence($allVerses);

// Apply correction: Verse 131 page -> 144
$correctedVerses = $allVerses;
foreach ($correctedVerses as &$verse) {
    if ($verse['verse_number'] === 131) {
        $verse['page_number'] = 144;
        foreach ($verse['words'] as &$word) {
            $word['page_number'] = 144;
        }
    }
}
unset($verse);

echo "\n=== AFTER TRANSFORMATION (Corrected / Validated Data) ===\n";
dumpSequence($correctedVerses);
