<?php

namespace App\Modules\Dataset\Validation;

class CorrectionReport
{
    public string $ruleIdentifier;
    public int $surahNumber;
    public int $verseNumber;
    public string $field;
    public mixed $oldValue;
    public mixed $newValue;
    public string $reason;

    public function __construct(
        string $ruleIdentifier,
        int $surahNumber,
        int $verseNumber,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        string $reason
    ) {
        $this->ruleIdentifier = $ruleIdentifier;
        $this->surahNumber = $surahNumber;
        $this->verseNumber = $verseNumber;
        $this->field = $field;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->reason = $reason;
    }

    public function toArray(): array
    {
        return [
            'rule' => $this->ruleIdentifier,
            'surah' => $this->surahNumber,
            'verse' => $this->verseNumber,
            'field' => $this->field,
            'old' => $this->oldValue,
            'new' => $this->newValue,
            'reason' => $this->reason,
        ];
    }
}
