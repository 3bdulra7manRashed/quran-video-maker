<?php

use App\Services\ContentGeneration\GeneratedContentWordAligner;
use App\Modules\Quran\Models\ReelsGeneratedContent;
use App\Modules\Quran\Models\Word;

$aligner = new GeneratedContentWordAligner();

$segments = collect([
    new ReelsGeneratedContent(['segment_order' => 1, 'arabic' => 'وازدجر ٩']),
    new ReelsGeneratedContent(['segment_order' => 2, 'arabic' => 'ففتحنا ابواب السماء']),
]);

$words = [
    new Word(['id' => 10, 'uthmani_text' => 'وَٱزْدُجِرَ']),
    new Word(['id' => 11, 'uthmani_text' => 'فَفَتَحْنَآ']),
    new Word(['id' => 12, 'uthmani_text' => 'أَبْوَابَ']),
    new Word(['id' => 13, 'uthmani_text' => 'ٱلْمَآءِ']),
];

$ranges = $aligner->align($segments, $words);
print_r($ranges);
