<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$mushafIds = [1, 2, 3, 4, 5, 6, 7, 11, 19];

foreach ($mushafIds as $mId) {
    echo "Querying with mushaf={$mId}...\n";
    $url = "https://api.quran.com/api/v4/verses/by_key/6:131?language=en&words=true&word_fields=code_v1,text_uthmani,text_imlaei&mushaf={$mId}";
    $response = Http::timeout(30)->get($url);
    if ($response->successful()) {
        $data = $response->json();
        $verse = $data['verse'] ?? null;
        if ($verse) {
            $vPage = $verse['page_number'] ?? 'null';
            $firstWordPage = $verse['words'][0]['page_number'] ?? 'null';
            $firstWordLine = $verse['words'][0]['line_number'] ?? 'null';
            $lastWordPage = end($verse['words'])['page_number'] ?? 'null';
            $lastWordLine = end($verse['words'])['line_number'] ?? 'null';
            echo "  Mushaf {$mId}: Verse Page={$vPage}, First Word Page={$firstWordPage} Line={$firstWordLine}, Last Word Page={$lastWordPage} Line={$lastWordLine}\n";
        } else {
            echo "  Mushaf {$mId}: No verse returned\n";
        }
    } else {
        echo "  Mushaf {$mId}: Request failed\n";
    }
}
