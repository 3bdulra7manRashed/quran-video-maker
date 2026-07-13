<?php

$destFile = __DIR__ . "/../storage/app/quran/glyph/surah_79.json";
$allVerses = json_decode(file_get_contents($destFile), true);

foreach ($allVerses['verses'] as $verse) {
    $vNum = $verse['verse_number'];
    if ($vNum >= 14 && $vNum <= 18) {
        $vPage = $verse['page_number'];
        echo "Verse {$vNum}: Page={$vPage}, Words start on Page=" . ($verse['words'][0]['page_number'] ?? 'null') . 
             " Line=" . ($verse['words'][0]['line_number'] ?? 'null') . 
             ", Words end on Page=" . (end($verse['words'])['page_number'] ?? 'null') . 
             " Line=" . (end($verse['words'])['line_number'] ?? 'null') . "\n";
    }
}
