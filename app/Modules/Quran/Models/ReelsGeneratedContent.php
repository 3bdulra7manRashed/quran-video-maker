<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\ContentApprovalStatus;
use App\Enums\GeneratorType;
use App\Enums\LayoutType;

class ReelsGeneratedContent extends Model
{
    protected $table = 'reels_generated_contents';

    protected $fillable = [
        'reciter_id',
        'surah_number',
        'start_ayah',
        'end_ayah',
        'layout_type',
        'segment_order',
        'arabic',
        'translation',
        'tafsir',
        'approval_status',
        'content_version',
        'generator_type',
        'generator_model',
        'generator_latency_ms',
        'prompt_version',
        'prompt_hash',
        'source_json',
        'generated_at',
    ];

    protected $casts = [
        'reciter_id' => 'integer',
        'surah_number' => 'integer',
        'start_ayah' => 'integer',
        'end_ayah' => 'integer',
        'segment_order' => 'integer',
        'content_version' => 'integer',
        'generator_latency_ms' => 'integer',
        'source_json' => 'array',
        'generated_at' => 'datetime',
        'layout_type' => LayoutType::class,
        'approval_status' => ContentApprovalStatus::class,
        'generator_type' => GeneratorType::class,
    ];

    public function reciter()
    {
        return $this->belongsTo(Reciter::class);
    }
}
