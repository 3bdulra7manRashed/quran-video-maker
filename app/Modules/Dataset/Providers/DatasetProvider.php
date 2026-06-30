<?php

namespace App\Modules\Dataset\Providers;

interface DatasetProvider
{
    /**
     * Determine if glyph data is already downloaded on disk.
     */
    public function determineGlyphsAvailability(int $surahNumber): bool;

    /**
     * Download glyph data from the external source.
     */
    public function downloadGlyphs(int $surahNumber): void;

    /**
     * Import glyph data from disk to the database.
     */
    public function importGlyphs(int $surahNumber): void;

    /**
     * Determine if audio data is already downloaded on disk.
     */
    public function determineAudioAvailability(string $reciterSlug, int $surahNumber): bool;

    /**
     * Download audio file from the external source.
     */
    public function downloadAudio(string $reciterSlug, int $surahNumber): void;

    /**
     * Determine if timing data is already downloaded on disk.
     */
    public function determineTimingsAvailability(string $reciterSlug, int $surahNumber): bool;

    /**
     * Download timings from the external source.
     */
    public function downloadTimings(string $reciterSlug, int $surahNumber): void;

    /**
     * Import timings from disk to the database.
     */
    public function importTimings(string $reciterSlug, int $surahNumber): void;
}
