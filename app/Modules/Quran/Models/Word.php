<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    protected $fillable = [
        'ayah_id',
        'word_index',
        'char_type',
        'plain_text',
        'uthmani_text',
        'glyph_text',
        'page_number',
        'line_number',
        'translation_en',
        // Deprecated. Will be removed after all timing readers and importers migrate to reciter_word_timings.
        'start_ms_from_surah',
        // Deprecated. Will be removed after all timing readers and importers migrate to reciter_word_timings.
        'end_ms_from_surah',
    ];

    protected $casts = [
        'ayah_id'             => 'integer',
        'word_index'          => 'integer',
        'page_number'         => 'integer',
        'line_number'         => 'integer',
        // Deprecated.
        'start_ms_from_surah' => 'integer',
        // Deprecated.
        'end_ms_from_surah'   => 'integer',
    ];

    public function ayah()
    {
        return $this->belongsTo(Ayah::class);
    }

    public function reciterWordTimings()
    {
        return $this->hasMany(ReciterWordTiming::class);
    }
}
