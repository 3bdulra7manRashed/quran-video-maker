<?php

namespace App\Modules\Dataset\Validation;

class ValidationReport
{
    protected bool $isValid = true;
    protected array $issues = [];

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function addIssue(ValidationIssue $issue): void
    {
        if ($issue->severity === 'error') {
            $this->isValid = false;
        }
        $this->issues[] = $issue;
    }

    /**
     * @return ValidationIssue[]
     */
    public function getIssues(): array
    {
        return $this->issues;
    }
}
