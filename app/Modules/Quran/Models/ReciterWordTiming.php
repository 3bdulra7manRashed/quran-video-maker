<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;

class ReciterWordTiming extends Model
{
    protected $table = 'reciter_word_timings';

    protected $fillable = [
        'reciter_id',
        'word_id',
        'start_ms',
        'end_ms',
    ];

    protected $casts = [
        'reciter_id' => 'integer',
        'word_id' => 'integer',
        'start_ms' => 'integer',
        'end_ms' => 'integer',
    ];

    public function reciter()
    {
        return $this->belongsTo(Reciter::class);
    }

    public function word()
    {
        return $this->belongsTo(Word::class);
    }
}
