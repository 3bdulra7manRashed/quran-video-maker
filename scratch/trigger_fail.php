<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Rendering\Services\RenderPipeline;

$pipeline = app(RenderPipeline::class);
try {
    echo "Running RenderPipeline...\n";
    $pipeline->render(54, 'yasser-al-dosari', 9, 17, 'reels', 1, false, 'sahih_international', null, true);
} catch (\Throwable $e) {
    echo "Caught: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
