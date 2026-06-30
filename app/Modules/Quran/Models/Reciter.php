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
        'source',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'source' => 'string',
    ];

    public function audioFiles()
    {
        return $this->hasMany(AudioFile::class);
    }

    public function wordTimings()
    {
        return $this->hasMany(ReciterWordTiming::class);
    }

    public function lineTimings()
    {
        return $this->hasMany(ReciterLineTiming::class);
    }
}
