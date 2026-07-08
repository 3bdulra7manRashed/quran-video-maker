<?php

$ids = [161, 168, 170, 172, 173, 174];

for ($page = 1; $page <= 5; $page++) {
    $url = "https://api.quran.com/api/v4/resources/recitations?page={$page}&per_page=100";
    $response = @file_get_contents($url);
    if (!$response) break;
    $data = json_decode($response, true);
    if (empty($data['recitations'])) break;
    
    foreach ($data['recitations'] as $recitation) {
        $id = $recitation['id'];
        if (in_array($id, $ids)) {
            print_r($recitation);
        }
    }
}
