<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$res = Http::withoutVerifying()->get('https://api.quran.com/api/v4/tafsirs/16/by_chapter/2?per_page=300')->json();
echo "Chapter 2 count with per_page=300: " . count($res['tafsirs'] ?? []) . "\n";
echo "Pagination info: " . json_encode($res['pagination'] ?? [], JSON_PRETTY_PRINT) . "\n";
