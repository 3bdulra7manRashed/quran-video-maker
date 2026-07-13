<?php

$destFile = __DIR__ . "/surah_6_raw_downloaded.json";
$allVerses = json_decode(file_get_contents($destFile), true);

foreach ($allVerses as $verse) {
    $vNum = $verse['verse_number'];
    if ($vNum >= 120 && $vNum <= 145) {
        $vPage = $verse['page_number'];
        echo "Verse {$vNum}: Page={$vPage}, Words start on Page=" . ($verse['words'][0]['page_number'] ?? 'null') . 
             " Line=" . ($verse['words'][0]['line_number'] ?? 'null') . 
             ", Words end on Page=" . (end($verse['words'])['page_number'] ?? 'null') . 
             " Line=" . (end($verse['words'])['line_number'] ?? 'null') . "\n";
    }
}
