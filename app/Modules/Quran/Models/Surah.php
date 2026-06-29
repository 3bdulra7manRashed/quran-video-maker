<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;

class Surah extends Model
{
    protected $fillable = [
        'number',
        'name_arabic',
        'name_simple',
        'surah_glyph',
        'verses_count',
        'start_page',
        'end_page',
    ];

    protected $casts = [
        'number'       => 'integer',
        'verses_count' => 'integer',
        'start_page'   => 'integer',
        'end_page'     => 'integer',
    ];

    public function ayahs()
    {
        return $this->hasMany(Ayah::class);
    }
}
