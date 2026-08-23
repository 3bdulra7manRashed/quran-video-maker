<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Rendering\Services\RenderPipeline;

echo "Rendering Surah 98 (Al-Bayyinah) verse 3 with Tafsir enabled...\n";

$pipeline = app(RenderPipeline::class);

try {
    $videoPath = $pipeline->render(
        surahNumber: 98,
        reciterSlug: 'yasser-al-dosari',
        fromAyah: 3,
        toAyah: 3,
        layout: 'reels',
        maxLines: 1,
        withTranslation: false,
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
