<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;

class Ayah extends Model
{
    protected $fillable = [
        'surah_id',
        'verse_key',
        'ayah_number',
        'page_number',
        'juz_number',
        'hizb_number',
    ];

    protected $casts = [
        'surah_id'    => 'integer',
        'ayah_number' => 'integer',
        'page_number' => 'integer',
        'juz_number'  => 'integer',
        'hizb_number' => 'integer',
    ];

    public function surah()
    {
        return $this->belongsTo(Surah::class);
    }

    public function words()
    {
        return $this->hasMany(Word::class);
    }
}
