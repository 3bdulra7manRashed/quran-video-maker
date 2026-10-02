<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t = App\Modules\Quran\Models\AyahTranslation::where('surah_number', 62)->where('ayah_number', 9)->first();
if ($t) {
    echo "Text: " . $t->text . "\n";
    echo "Hex: " . bin2hex($t->text) . "\n";
} else {
    echo "Not found in DB\n";
    // Check json file
    $jsonPath = storage_path('app/quran/translations/sahih_international.json');
    if (file_exists($jsonPath)) {
        $data = json_decode(file_get_contents($jsonPath), true);
        echo "Found json file with keys: " . count($data) . "\n";
    }
}
