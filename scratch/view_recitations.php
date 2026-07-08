<?php

$data = json_decode(file_get_contents('scratch/recitations_all.json'), true);
echo "Count: " . count($data['recitations']) . "\n";
foreach ($data['recitations'] as $r) {
    echo "ID: {$r['id']} | Name: {$r['reciter_name']} | Translated Name: " . ($r['translated_name']['name'] ?? '') . "\n";
}
