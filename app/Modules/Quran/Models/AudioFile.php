<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;

class AudioFile extends Model
{
    protected $fillable = [
        'reciter_id',
        'surah_number',
        'file_path',
        'duration_ms',
    ];

    protected $casts = [
        'reciter_id'   => 'integer',
        'surah_number' => 'integer',
        'duration_ms'  => 'integer',
    ];

    public function reciter()
    {
        return $this->belongsTo(Reciter::class);
    }
}
