<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;

class ReciterLineTiming extends Model
{
    protected $table = 'reciter_line_timings';

    protected $fillable = [
        'reciter_id',
        'surah_number',
        'line_number',
        'start_ms',
    ];

    protected $casts = [
        'reciter_id' => 'integer',
        'surah_number' => 'integer',
        'line_number' => 'integer',
        'start_ms' => 'integer',
    ];

    public function reciter()
    {
        return $this->belongsTo(Reciter::class);
    }
}
