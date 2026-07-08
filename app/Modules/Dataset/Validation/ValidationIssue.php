<?php

namespace App\Modules\Dataset\Validation;

class ValidationIssue
{
    public string $identifier;
    public string $severity; // 'error' | 'warning'
    public string $message;
    public ?string $location;
    public ?int $surahNumber;
    public ?int $verseNumber;
    public ?int $pageNumber;
    public ?int $lineNumber;

    public function __construct(
        string $identifier,
        string $severity,
        string $message,
        ?string $location = null,
        ?int $surahNumber = null,
        ?int $verseNumber = null,
        ?int $pageNumber = null,
        ?int $lineNumber = null
    ) {
        $this->identifier = $identifier;
        $this->severity = $severity;
        $this->message = $message;
        $this->location = $location;
        $this->surahNumber = $surahNumber;
        $this->verseNumber = $verseNumber;
        $this->pageNumber = $pageNumber;
        $this->lineNumber = $lineNumber;
    }

    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'severity' => $this->severity,
            'message' => $this->message,
            'location' => $this->location,
            'surah' => $this->surahNumber,
            'verse' => $this->verseNumber,
            'page' => $this->pageNumber,
            'line' => $this->lineNumber,
        ];
    }
}
