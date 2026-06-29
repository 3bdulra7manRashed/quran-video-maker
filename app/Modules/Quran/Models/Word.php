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
        'start_ms_from_surah',
        'end_ms_from_surah',
    ];

    protected $casts = [
        'ayah_id'             => 'integer',
        'word_index'          => 'integer',
        'page_number'         => 'integer',
        'line_number'         => 'integer',
        'start_ms_from_surah' => 'integer',
        'end_ms_from_surah'   => 'integer',
    ];

    public function ayah()
    {
        return $this->belongsTo(Ayah::class);
    }
}
