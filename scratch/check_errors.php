<?php
$p = storage_path('app/quran/timings/timings_2_252.json');
$data = json_decode(file_get_contents($p), true);
print_r($data);
