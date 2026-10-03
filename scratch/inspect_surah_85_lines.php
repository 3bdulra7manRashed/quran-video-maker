<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Ayah;
use App\Modules\Quran\Models\Word;
use App\Modules\Rendering\Services\LineTimingResolver;

$reciter = Reciter::where('slug', 'yasser-al-dosari')->first();
$resolver = app(LineTimingResolver::class);

$ayahs = Ayah::where('surah_id', 85)->orderBy('ayah_number')->get();
$ayahIds = $ayahs->pluck('id');
$words = Word::whereIn('ayah_id', $ayahIds)->orderBy('id')->get()->all();

// Line groups for page 590
$lineGroups = [];
foreach ($words as $w) {
    $lineGroups[$w->line_number][] = $w;
}
ksort($lineGroups);

$lineTimings = $resolver->resolve($reciter, 85, $words, 126.8, array_values($lineGroups));

echo "Resolved line timings for Surah 85 (Page 590):\n";
foreach ($lineTimings as $lineNum => $startMs) {
    echo "  Line {$lineNum}: start = {$startMs}ms (" . round($startMs / 1000, 2) . "s)\n";
}
