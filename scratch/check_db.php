<?php

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use App\Modules\Quran\Models\ReelsGeneratedContent;
use App\Modules\Quran\Models\Word;
use App\Modules\Quran\Models\ReciterWordTiming;

$reciterCount = Reciter::count();
$surahCount = Surah::count();
$generatedCount = ReelsGeneratedContent::count();
$approvedCount = ReelsGeneratedContent::where('approval_status', \App\Enums\ContentApprovalStatus::APPROVED)->count();

echo "Reciters: $reciterCount\n";
echo "Surahs: $surahCount\n";
echo "Generated Contents: $generatedCount\n";
echo "Approved Contents: $approvedCount\n";

if ($reciterCount > 0) {
    echo "\nReciters:\n";
    foreach (Reciter::all() as $r) {
        echo " - ID: {$r->id}, Slug: {$r->slug}, Name: {$r->name_english}\n";
    }
}

if ($approvedCount > 0) {
    echo "\nApproved Content Samples:\n";
    foreach (ReelsGeneratedContent::where('approval_status', \App\Enums\ContentApprovalStatus::APPROVED)->take(5)->get() as $c) {
        echo " - ID: {$c->id}, Reciter ID: {$c->reciter_id}, Surah: {$c->surah_number}, Range: {$c->start_ayah}-{$c->end_ayah}, Layout: {$c->layout_type->value}, Order: {$c->segment_order}, Arabic: {$c->arabic}\n";
    }
}
