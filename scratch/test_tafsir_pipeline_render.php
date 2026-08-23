<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Rendering\Services\RenderPipeline;

echo "Testing RenderPipeline with Tafsir enabled for Surah 108 (Al-Kawthar)...\n";

$pipeline = app(RenderPipeline::class);

try {
    $videoPath = $pipeline->render(
        surahNumber: 108,
        reciterSlug: 'yasser-al-dosari',
        fromAyah: 1,
        toAyah: 3,
        layout: 'reels',
        maxLines: 1,
        withTranslation: true,
        translationSource: 'sahih_international',
        renderJob: null,
        useGeneratedContent: false,
        withTafsir: true,
        tafsirSource: 'ar-tafsir-muyassar'
    );

    echo "SUCCESS! Rendered video at: {$videoPath}\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
