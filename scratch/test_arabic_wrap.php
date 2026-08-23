<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use ArPHP\I18N\Arabic;

$arabic = new Arabic('Glyphs');
$fontPath = base_path('fonts/Cairo-Regular.ttf');
$fontSize = 28;
$maxWidth = 760;

$text = "أبتدئ قراءة القرآن باسم الله مستعينا به، الله علم على الرب -تبارك وتعالى- المعبود بحق دون سواه، وهو أخص أسماء الله تعالى، ولا يسمى به غيره سبحانه.";

$words = explode(' ', $text);
$lines = [];
$currentWords = [];

foreach ($words as $word) {
    $testWords = array_merge($currentWords, [$word]);
    $testLogicalText = implode(' ', $testWords);
    $testShapedText = $arabic->utf8Glyphs($testLogicalText);
    
    $bbox = imagettfbbox($fontSize, 0, $fontPath, $testShapedText);
    $width = abs($bbox[4] - $bbox[0]);
    
    if ($width <= $maxWidth) {
        $currentWords[] = $word;
    } else {
        if (!empty($currentWords)) {
            $lines[] = $arabic->utf8Glyphs(implode(' ', $currentWords));
        }
        $currentWords = [$word];
    }
}
if (!empty($currentWords)) {
    $lines[] = $arabic->utf8Glyphs(implode(' ', $currentWords));
}

echo "Line count: " . count($lines) . "\n";
foreach ($lines as $i => $line) {
    echo "Line #{$i}: {$line}\n";
}
