<?php

namespace App\Modules\Layout\DTO;

class Frame
{
    public int $index;
    public array $lines;
    public int $startLine;
    public int $endLine;
    public int $startMs;
    public int $endMs;

    public function __construct(
        int $index,
        array $lines,
        int $startLine,
        int $endLine,
        int $startMs = 0,
        int $endMs = 0
    ) {
        $this->index = $index;
        $this->lines = $lines;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
        $this->startMs = $startMs;
        $this->endMs = $endMs;
    }
}
