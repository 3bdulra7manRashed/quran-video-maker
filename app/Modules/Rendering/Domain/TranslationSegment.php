<?php

namespace App\Modules\Rendering\Domain;

class TranslationSegment
{
    public int $surahNumber;
    public string $text;
    public int $fromAyah;
    public int $toAyah;
    public int $startMs;
    public int $endMs;

    public function __construct(
        int $surahNumber,
        string $text,
        int $fromAyah,
        int $toAyah,
        int $startMs,
        int $endMs
    ) {
        $this->surahNumber = $surahNumber;
        $this->text = $text;
        $this->fromAyah = $fromAyah;
        $this->toAyah = $toAyah;
        $this->startMs = $startMs;
        $this->endMs = $endMs;
    }
}
