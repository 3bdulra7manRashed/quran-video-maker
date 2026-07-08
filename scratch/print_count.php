<?php

$url = 'https://api.quran.com/api/v4/resources/recitations?per_page=100';
$response = file_get_contents($url);
$data = json_decode($response, true);
echo "Count: " . count($data['recitations']) . "\n";
foreach ($data['recitations'] as $r) {
    echo "ID: {$r['id']} | Name: {$r['reciter_name']}\n";
}
