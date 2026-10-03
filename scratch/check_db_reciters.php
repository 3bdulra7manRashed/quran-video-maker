<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$reciters = DB::table('reciters')->get();
echo "Total reciters in database: " . $reciters->count() . "\n\n";

if ($reciters->isNotEmpty()) {
    $cols = array_keys((array)$reciters->first());
    echo "Columns: " . implode(', ', $cols) . "\n\n";
}

foreach ($reciters as $r) {
    $wtCount = DB::table('reciter_word_timings')->where('reciter_id', $r->id)->count();
    $audioCount = DB::table('audio_files')->where('reciter_id', $r->id)->count();
    $name = $r->name_ar ?? ($r->name_en ?? ($r->slug ?? ''));
    echo sprintf(
        "ID: %-3d | Slug: %-25s | Name: %-30s | Word Timings: %-6d | Audio Files: %d\n",
        $r->id,
        $r->slug ?? '',
        $name,
        $wtCount,
        $audioCount
    );
}
