<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$jobs = DB::table('render_jobs')
    ->orderByDesc('id')
    ->limit(5)
    ->get();

echo "=== Last 5 Render Jobs ===\n";
foreach ($jobs as $j) {
    echo "ID: {$j->id} | UUID: {$j->uuid} | Surah: {$j->surah_number} | Reciter: {$j->reciter_id} | Status: {$j->status} | Layout: {$j->layout}\n";
    echo "  Created: {$j->created_at} | Scope: " . ($j->from_ayah ?? 'null') . "-" . ($j->to_ayah ?? 'null') . "\n";
    echo "  Theme: " . ($j->theme ?? 'null') . " | Custom Content: " . ($j->use_generated_content ? 'YES' : 'NO') . "\n";
    if (!empty($j->error_message)) {
        echo "  Error: {$j->error_message}\n";
    }
}
