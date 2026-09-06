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

    public const SLUG_ALIASES = [
        'mishari-rashid-al-afasy' => 'mishari-al-afasy',
        'mishari-al-afasy'        => 'mishari-al-afasy',
        'yasser-aldosari'          => 'yasser-al-dosari',
        'yasser-al-dosari'         => 'yasser-al-dosari',
    ];

    /**
     * Normalize reciter slug to canonical database representation.
     */
    public static function normalizeSlug(string $slug): string
    {
        $normalized = trim(strtolower($slug));
        $normalized = str_replace('_', '-', $normalized);
        return self::SLUG_ALIASES[$normalized] ?? $normalized;
    }

    /**
     * Find a reciter by canonical slug or supported alias.
     */
    public static function findBySlug(string $slug): ?self
    {
        $canonical = self::normalizeSlug($slug);
        return static::where('slug', $canonical)
            ->orWhere('slug', $slug)
            ->first();
    }

    /**
     * Scope query to find by slug or alias.
     */
    public function scopeBySlug($query, string $slug)
    {
        $canonical = self::normalizeSlug($slug);
        return $query->where(function ($q) use ($canonical, $slug) {
            $q->where('slug', $canonical)->orWhere('slug', $slug);
        });
    }

    public function lineTimings()
    {
        return $this->hasMany(ReciterLineTiming::class);
    }
}
