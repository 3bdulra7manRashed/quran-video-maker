<?php

$url = 'https://api.quran.com/api/v4/resources/chapter_reciters';
$response = file_get_contents($url);
$data = json_decode($response, true);

echo "Total chapter reciters found: " . count($data['reciters']) . "\n";
echo "Search results for Jaber/جابر:\n";
foreach ($data['reciters'] as $reciter) {
    $id = $reciter['id'];
    $name = $reciter['reciter_name'] ?? $reciter['name'] ?? '';
    $arName = $reciter['translated_name']['name'] ?? '';
    
    if (stripos($name, 'jaber') !== false || stripos($arName, 'جابر') !== false) {
        echo "ID: {$id} | EN: {$name} | AR: {$arName}\n";
    }
}
