<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$columns = Schema::getColumnListing('reciters');
echo "Columns: " . implode(', ', $columns) . "\n";

$reciters = DB::table('reciters')->get();
echo "Total reciters in DB: " . $reciters->count() . "\n";
foreach ($reciters as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
