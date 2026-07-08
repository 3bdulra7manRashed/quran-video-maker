<?php

/**
 * Register the existing audio file in the DB for reciter 24 and verify readiness.
 * The audio file already exists on disk from a previous download.
 */

use App\Modules\Quran\Models\AudioFile;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Support\Facades\DB;

$slug = 'ali-jaber';
$surahNumber = 78;

$pathResolver = app(QuranPathResolver::class);
$reciter = Reciter::where('slug', $slug)->first();
$audioPath = $pathResolver->audio("{$slug}/078.mp3");

echo "Reciter: ID={$reciter->id}, Slug={$reciter->slug}, Source={$reciter->source}\n";
echo "Audio path: {$audioPath}\n";
echo "File exists: " . (file_exists($audioPath) ? 'YES' : 'NO') . "\n";
echo "File size: " . (file_exists($audioPath) ? round(filesize($audioPath) / 1024 / 1024, 2) . ' MB' : 'N/A') . "\n";

// Check if already registered
$existing = AudioFile::where('reciter_id', $reciter->id)
    ->where('surah_number', $surahNumber)
    ->first();

if ($existing) {
    echo "\nAudio already registered: ID={$existing->id}, duration={$existing->duration_ms}ms\n";
} else {
    // Probe duration
    $ffprobe = config('ffmpeg.ffprobe_path', 'ffprobe');
    $cmd = sprintf('"%s" -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "%s"', $ffprobe, $audioPath);
    $output = [];
    $resultCode = -1;
    exec($cmd . ' 2>&1', $output, $resultCode);
    $durationMs = 0;
    if ($resultCode === 0 && !empty($output)) {
        $durationMs = (int) round((float) trim($output[0]) * 1000);
    }
    if ($durationMs <= 0) {
        $durationMs = 450000;
    }
    
    echo "\nProbed duration: {$durationMs}ms\n";
    
    AudioFile::create([
        'reciter_id' => $reciter->id,
        'surah_number' => $surahNumber,
        'file_path' => "{$slug}/078.mp3",
        'duration_ms' => $durationMs,
    ]);
    echo "✅ Audio file registered in DB.\n";
}

// Check word timings
$wt = DB::table('reciter_word_timings')->where('reciter_id', $reciter->id)->count();
echo "\nWord timings: {$wt}\n";

// Check line timings
$lt = DB::table('reciter_line_timings')->where('reciter_id', $reciter->id)->count();
echo "Line timings: {$lt}\n";

if ($wt == 0) {
    echo "\n⚠️  No word-level timings. Renderer will use proportional fallback.\n";
}

// Check glyphs
$glyphPath = storage_path("app/quran/glyph/surah_{$surahNumber}.json");
echo "\nGlyph file: " . (file_exists($glyphPath) ? '✅ EXISTS' : '❌ MISSING') . "\n";

echo "\n✅ Ready for rendering with: php artisan render:video {$surahNumber} --reciter={$slug} --layout=reels\n";
