<?php

$destFile = __DIR__ . "/surah_6_raw_downloaded.json";
$allVerses = json_decode(file_get_contents($destFile), true);

foreach ($allVerses as $verse) {
    $vNum = $verse['verse_number'];
    if ($vNum >= 131 && $vNum <= 132) {
        $vPage = $verse['page_number'];
        echo "Verse {$vNum}: Key={$verse['verse_key']}, Page={$vPage}, Word Count=" . count($verse['words']) . "\n";
        foreach ($verse['words'] as $w) {
            $wPos = $w['position'];
            $wPage = $w['page_number'] ?? 'null';
            $wLine = $w['line_number'] ?? 'null';
            $wGlyph = $w['code_v1'] ?? 'null';
            $wText = $w['text_uthmani'] ?? 'null';
            echo "  Word {$wPos}: Page={$wPage}, Line={$wLine}, Glyph='{$wGlyph}', Text='{$wText}'\n";
        }
    }
}
