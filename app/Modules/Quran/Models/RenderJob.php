<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RenderJob extends Model
{
    protected $table = 'render_jobs';

    protected $fillable = [
        'uuid',
        'status', // pending, processing, completed, failed
        'reciter_id',
        'surah_number',
        'from_ayah',
        'to_ayah',
        'output_path',
        'output_filename',
        'error_message',
        'started_at',
        'completed_at',
        'finished_at',
        'progress',
        'duration',
        'layout',
        'theme',
        'max_lines',
        'with_translation',
        'translation_source',
        'with_tafsir',
        'tafsir_source',
        'use_generated_content',
    ];

    protected $casts = [
        'reciter_id' => 'integer',
        'surah_number' => 'integer',
        'from_ayah' => 'integer',
        'to_ayah' => 'integer',
        'progress' => 'integer',
        'duration' => 'float',
        'max_lines' => 'integer',
        'with_translation' => 'boolean',
        'with_tafsir' => 'boolean',
        'use_generated_content' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function reciter()
    {
        return $this->belongsTo(Reciter::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCancelling(): bool
    {
        return $this->status === 'cancelling';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
