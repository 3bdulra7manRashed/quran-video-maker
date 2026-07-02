<?php

namespace App\Services\ContentGeneration\Exceptions;

use RuntimeException;

class AlignmentException extends RuntimeException
{
    protected int $segmentOrder;
    protected string $segmentArabic;
    protected string $normalizedSegment;
    protected array $matchedWords;
    protected int $startIndex;
    protected int $currentIndex;

    public function __construct(
        string $message,
        int $segmentOrder,
        string $segmentArabic,
        string $normalizedSegment,
        array $matchedWords,
        int $startIndex,
        int $currentIndex
    ) {
        parent::__construct($message);
        $this->segmentOrder = $segmentOrder;
        $this->segmentArabic = $segmentArabic;
        $this->normalizedSegment = $normalizedSegment;
        $this->matchedWords = $matchedWords;
        $this->startIndex = $startIndex;
        $this->currentIndex = $currentIndex;
    }

    public function getSegmentOrder(): int
    {
        return $this->segmentOrder;
    }

    public function getSegmentArabic(): string
    {
        return $this->segmentArabic;
    }

    public function getNormalizedSegment(): string
    {
        return $this->normalizedSegment;
    }

    public function getMatchedWords(): array
    {
        return $this->matchedWords;
    }

    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    public function getCurrentIndex(): int
    {
        return $this->currentIndex;
    }
}
