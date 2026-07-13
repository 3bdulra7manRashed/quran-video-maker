<?php

$destFile = __DIR__ . "/surah_6_raw_downloaded.json";
$allVerses = json_decode(file_get_contents($destFile), true);

foreach ($allVerses as $verse) {
    if ($verse['verse_number'] === 131) {
        $firstWord = $verse['words'][0];
        echo "Word keys:\n";
        print_r(array_keys($firstWord));
        echo "\nWord values:\n";
        print_r($firstWord);
        break;
    }
}
