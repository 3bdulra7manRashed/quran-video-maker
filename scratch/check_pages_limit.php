<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Quran\Models\Surah;

$surahs = Surah::whereIn('number', [6, 55, 79])->get();
foreach ($surahs as $s) {
    echo "Surah {$s->number}: start_page={$s->start_page}, end_page={$s->end_page}\n";
}
