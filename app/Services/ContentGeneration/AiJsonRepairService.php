<?php

namespace App\Services\ContentGeneration;

class AiJsonRepairService
{
    /**
     * Repair common JSON serialization mistakes in the text (like unescaped quotes).
     *
     * @param string $json
     * @return string
     */
    public function repair(string $json): string
    {
        // If already valid JSON, do not modify
        if ($this->isValidJson($json)) {
            return $json;
        }

        $lines = explode("\n", $json);
        foreach ($lines as &$line) {
            // Targets schema fields: arabic, translation, or tafsir
            // The regex captures key (matches[1]), the inner value string (matches[2]), and optional comma/braces (matches[3]).
            if (preg_match('/^\s*"(arabic|translation|tafsir)"\s*:\s*"(.*)"\s*(,?)\s*$/', $line, $matches)) {
                $key = $matches[1];
                $val = $matches[2];
                $comma = $matches[3];

                // Normalize any existing escapes to avoid double-escaping
                $normalized = str_replace('\"', '"', $val);

                // Escape all inner quotes
                $escaped = str_replace('"', '\"', $normalized);

                // Preserve indentation
                preg_match('/^(\s*)/', $line, $indentMatches);
                $indent = $indentMatches[1] ?? '';

                $line = $indent . '"' . $key . '": "' . $escaped . '"' . $comma;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Check if the given string is valid JSON.
     *
     * @param string $json
     * @return bool
     */
    private function isValidJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
