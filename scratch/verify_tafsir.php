<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Quran\Models\AyahTranslation;

$count = AyahTranslation::where('source', 'ar-tafsir-muyassar')->count();
echo "Total 'ar-tafsir-muyassar' records in DB: {$count}\n";

$sample = AyahTranslation::where('source', 'ar-tafsir-muyassar')->where('surah_number', 108)->get();
foreach ($sample as $s) {
    echo "Surah 108:{$s->ayah_number} => {$s->text}\n";
}
