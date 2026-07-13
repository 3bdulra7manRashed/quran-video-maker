<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\Ayah;

$surah = Surah::where('number', 6)->first();
if ($surah) {
    echo "Surah 6 exists in DB. ID: {$surah->id}\n";
    $ayahsCount = Ayah::where('surah_id', $surah->id)->count();
    echo "Total ayahs in DB for Surah 6: {$ayahsCount}\n";
} else {
    echo "Surah 6 does NOT exist in DB.\n";
}
