<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;

class AyahTranslation extends Model
{
    protected $fillable = [
        'source',
        'surah_number',
        'ayah_number',
        'text',
    ];
}
