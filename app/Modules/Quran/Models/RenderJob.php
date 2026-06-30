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
    ];

    protected $casts = [
        'reciter_id' => 'integer',
        'surah_number' => 'integer',
        'from_ayah' => 'integer',
        'to_ayah' => 'integer',
        'progress' => 'integer',
        'duration' => 'float',
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
}
