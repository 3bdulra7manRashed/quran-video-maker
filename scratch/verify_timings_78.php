<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Check words table columns
echo "words columns: " . implode(', ', Schema::getColumnListing('words')) . "\n";
echo "reciter_word_timings columns: " . implode(', ', Schema::getColumnListing('reciter_word_timings')) . "\n";
echo "reciter_line_timings columns: " . implode(', ', Schema::getColumnListing('reciter_line_timings')) . "\n";
echo "audio_files columns: " . implode(', ', Schema::getColumnListing('audio_files')) . "\n";
echo "dataset_metadata columns: " . implode(', ', Schema::getColumnListing('dataset_metadata')) . "\n";

// Word timings count
$wt = DB::table('reciter_word_timings')->where('reciter_id', 24)->count();
echo "\nWord timings for reciter 24: {$wt}\n";

// Line timings count
$lt = DB::table('reciter_line_timings')->where('reciter_id', 24)->count();
echo "Line timings for reciter 24: {$lt}\n";

// Audio files
$af = DB::table('audio_files')->where('reciter_id', 24)->get();
echo "\nAudio files for reciter 24:\n";
foreach ($af as $a) {
    $cols = (array)$a;
    echo "  " . json_encode($cols) . "\n";
}

// Dataset metadata
$dm = DB::table('dataset_metadata')->where('reciter_id', 24)->get();
echo "\nDataset metadata for reciter 24:\n";
foreach ($dm as $d) {
    $cols = (array)$d;
    echo "  " . json_encode($cols) . "\n";
}

// Check timing files on disk
$timingPath = storage_path("app/datasets/timings/ali-jaber");
echo "\nTiming directory: {$timingPath}\n";
echo "Exists: " . (is_dir($timingPath) ? 'YES' : 'NO') . "\n";
if (is_dir($timingPath)) {
    $files = scandir($timingPath);
    $files = array_filter($files, fn($f) => $f !== '.' && $f !== '..');
    echo "Files: " . count($files) . "\n";
    // Show files matching surah 78
    foreach ($files as $f) {
        if (str_contains($f, '78') || str_contains($f, '078')) {
            echo "  Found: {$f}\n";
        }
    }
    // Show first 5 files
    echo "First 5 files: " . implode(', ', array_slice(array_values($files), 0, 5)) . "\n";
}

// Check audio files on disk
$audioPath = storage_path("app/datasets/audio/ali-jaber");
echo "\nAudio directory: {$audioPath}\n";
echo "Exists: " . (is_dir($audioPath) ? 'YES' : 'NO') . "\n";
if (is_dir($audioPath)) {
    $files = scandir($audioPath);
    $files = array_filter($files, fn($f) => $f !== '.' && $f !== '..');
    echo "Files: " . count($files) . "\n";
    foreach ($files as $f) {
        if (str_contains($f, '78') || str_contains($f, '078')) {
            echo "  Found: {$f}\n";
        }
    }
}
