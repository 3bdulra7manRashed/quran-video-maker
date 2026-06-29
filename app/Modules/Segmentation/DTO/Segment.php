<?php

namespace App\Modules\Segmentation\DTO;

class Segment
{
    public int $index;
    public int $startWordId;
    public int $endWordId;
    public array $wordIds;
    public int $startMs;
    public int $endMs;
    public int $durationMs;
    
    // Loaded Word model collection for rendering convenience
    public array $words = [];

    // Debugging and rendering metrics
    public ?int $renderedWidth = null;
    public ?int $fontSize = null;
    public ?int $wordCount = null;

    public function __construct(
        int $index,
        int $startWordId,
        int $endWordId,
        array $wordIds,
        int $startMs,
        int $endMs,
        array $words = []
    ) {
        $this->index = $index;
        $this->startWordId = $startWordId;
        $this->endWordId = $endWordId;
        $this->wordIds = $wordIds;
        $this->startMs = $startMs;
        $this->endMs = $endMs;
        $this->durationMs = $endMs - $startMs;
        $this->words = $words;
    }
}
