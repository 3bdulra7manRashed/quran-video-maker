<?php

$url = 'https://api.quran.com/api/v4/resources/recitations?per_page=250';
$response = file_get_contents($url);
file_put_contents('scratch/recitations_all.json', $response);
echo "Saved all recitations to scratch/recitations_all.json\n";
