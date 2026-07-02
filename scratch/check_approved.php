<?php

use App\Modules\Quran\Models\ReelsGeneratedContent;

$records = ReelsGeneratedContent::where('reciter_id', 1)
    ->where('surah_number', 54)
    ->where('start_ayah', 9)
    ->where('end_ayah', 17)
    ->where('layout_type', 'reels')
    ->where('approval_status', \App\Enums\ContentApprovalStatus::APPROVED)
    ->orderBy('segment_order')
    ->get();

echo "Approved segments in DB:\n";
foreach ($records as $r) {
    echo "Order: {$r->segment_order}\n";
    echo "  Arabic: '{$r->arabic}'\n";
    echo "  Translation: '{$r->translation}'\n";
}
