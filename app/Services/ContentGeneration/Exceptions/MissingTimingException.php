<?php

namespace App\Services\ContentGeneration\Exceptions;

use RuntimeException;

class MissingTimingException extends RuntimeException
{
    protected int $segmentOrder;
    protected int $startIndex;
    protected int $endIndex;
    protected array $missingWordIds;
    protected array $missingWords;

    public function __construct(
        string $message,
        int $segmentOrder,
        int $startIndex,
        int $endIndex,
        array $missingWordIds,
        array $missingWords
    ) {
        parent::__construct($message);
        $this->segmentOrder = $segmentOrder;
        $this->startIndex = $startIndex;
        $this->endIndex = $endIndex;
        $this->missingWordIds = $missingWordIds;
        $this->missingWords = $missingWords;
    }

    public function getSegmentOrder(): int
    {
        return $this->segmentOrder;
    }

    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    public function getEndIndex(): int
    {
        return $this->endIndex;
    }

    public function getMissingWordIds(): array
    {
        return $this->missingWordIds;
    }

    public function getMissingWords(): array
    {
        return $this->missingWords;
    }
}
