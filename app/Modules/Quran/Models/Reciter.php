<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;

class Reciter extends Model
{
    protected $fillable = [
        'name_arabic',
        'name_english',
        'slug',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function audioFiles()
    {
        return $this->hasMany(AudioFile::class);
    }
}
