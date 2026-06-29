<?php

namespace App\Modules\Layout\Services;

use App\Modules\Layout\DTO\Frame;

class PaginationService
{
    // Layout parameters
    protected int $canvasHeight = 1920;
    protected int $topMargin = 150;
    protected int $headerHeight = 130;  // Space for Surah calligraphy
    protected int $reciterHeight = 100; // Space for reciter name
    protected int $lineSpacing = 80;    // Margin below header area
    protected int $bottomMargin = 120;
    protected int $lineHeight = 120;     // Baseline to baseline height for Quran lines

    /**
     * Paginate a sequential list of Quran lines into Frame objects based on layout dimensions.
     *
     * @param array $lines List of lines to paginate.
     * @return Frame[]
     */
    public function paginate(array $lines): array
    {
        $startY = $this->topMargin + $this->headerHeight + $this->reciterHeight + $this->lineSpacing;
        $maxY = $this->canvasHeight - $this->bottomMargin;
        
        $availableHeight = $maxY - $startY;
        
        // Calculate max lines that fit on a single frame dynamically
        $maxLinesPerFrame = (int) floor($availableHeight / $this->lineHeight);
        
        if ($maxLinesPerFrame <= 0) {
            $maxLinesPerFrame = 1; // Fallback safety
        }

        $frames = [];
        $chunks = array_chunk($lines, $maxLinesPerFrame);

        $lineCounter = 1;
        foreach ($chunks as $index => $chunkLines) {
            $frameIndex = $index + 1;
            $startLine = $lineCounter;
            $endLine = $lineCounter + count($chunkLines) - 1;
            
            $frames[] = new Frame(
                $frameIndex,
                $chunkLines,
                $startLine,
                $endLine
            );
            
            $lineCounter += count($chunkLines);
        }

        return $frames;
    }
}
