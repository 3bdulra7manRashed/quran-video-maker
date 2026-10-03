<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "Checking Maher Al-Muaiqly on Quran.com...\n";
// Let's check ID 12 or search for Maher
$res1 = Http::withoutVerifying()->get("https://api.quran.com/api/v4/chapter_recitations/12/1?segments=true");
echo "ID 12 Surah 1 status: " . $res1->status() . "\n";
if ($res1->successful()) {
    $ts = $res1->json('audio_file.timestamps') ?? [];
    echo "ID 12 has segments: " . (!empty($ts[0]['segments']) ? 'YES' : 'NO') . "\n";
}
