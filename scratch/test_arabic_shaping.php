<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use ArPHP\I18N\Arabic;

$arabic = new Arabic('Glyphs');

$sampleText = "في تلك الصحف كتب قيمة مصدقة";
$shaped = $arabic->utf8Glyphs($sampleText);

echo "Original: {$sampleText}\n";
echo "Shaped:   {$shaped}\n";
