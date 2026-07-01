<?php

namespace App\Modules\Quran\Models;

use Illuminate\Database\Eloquent\Model;

class DatasetMetadata extends Model
{
    protected $table = 'dataset_metadata';

    protected $fillable = [
        'reciter_id',
        'surah_number',
        'from_ayah',
        'to_ayah',
    ];

    protected $casts = [
        'reciter_id' => 'integer',
        'surah_number' => 'integer',
        'from_ayah' => 'integer',
        'to_ayah' => 'integer',
    ];

    public function reciter()
    {
        return $this->belongsTo(Reciter::class);
    }
}
