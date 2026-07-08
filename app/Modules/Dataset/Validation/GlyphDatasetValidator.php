<?php

namespace App\Modules\Dataset\Validation;

class GlyphDatasetValidator implements DatasetValidatorInterface
{
    public function validate(int $surahNumber, array $dataset): ValidationReport
    {
        $report = new ValidationReport();

        if (!isset($dataset['verses']) || !is_array($dataset['verses'])) {
            $report->addIssue(new ValidationIssue(
                'missing_verses_root',
                'error',
                'Dataset is missing a non-empty "verses" root array.',
                'root',
                $surahNumber
            ));
            return $report;
        }

        $flatWords = [];
        $lastVerseNum = null;
        $lastVersePage = null;

        // Traverse verses
        foreach ($dataset['verses'] as $vIndex => $verse) {
            $vNum = $verse['verse_number'] ?? null;
            $vp = $verse['page_number'] ?? null;

            if ($vNum === null || $vp === null) {
                $report->addIssue(new ValidationIssue(
                    'invalid_verse_metadata',
                    'error',
                    "Verse at index {$vIndex} has missing verse_number or page_number.",
                    "verses.{$vIndex}",
                    $surahNumber,
                    $vNum,
                    $vp
                ));
                continue;
            }

            // Rule 2: Verse ordering inconsistent with page ordering (and chronological checks)
            if ($lastVerseNum !== null && $vNum <= $lastVerseNum) {
                $report->addIssue(new ValidationIssue(
                    'verse_sequence_regression',
                    'error',
                    "Verse number went backward or duplicated: from {$lastVerseNum} to {$vNum}.",
                    "verses.{$vIndex}",
                    $surahNumber,
                    $vNum,
                    $vp
                ));
            }
            if ($lastVersePage !== null && $vp < $lastVersePage) {
                $report->addIssue(new ValidationIssue(
                    'verse_page_regression',
                    'error',
                    "Verse page number went backward: from {$lastVersePage} to {$vp} at Verse {$vNum}.",
                    "verses.{$vIndex}",
                    $surahNumber,
                    $vNum,
                    $vp
                ));
            }

            $lastVerseNum = $vNum;
            $lastVersePage = $vp;

            if (!isset($verse['words']) || !is_array($verse['words'])) {
                $report->addIssue(new ValidationIssue(
                    'missing_verse_words',
                    'error',
                    "Verse {$vNum} is missing its 'words' list.",
                    "verses.{$vIndex}",
                    $surahNumber,
                    $vNum,
                    $vp
                ));
                continue;
            }

            foreach ($verse['words'] as $wIndex => $w) {
                // Rule 3: Word page differing from verse page
                $wp = $w['page_number'] ?? null;
                if ($wp !== null && $wp !== $vp) {
                    $report->addIssue(new ValidationIssue(
                        'word_verse_page_mismatch',
                        'error',
                        "Word page number ($wp) differs from parent verse page number ($vp) at Verse $vNum position {$w['position']}.",
                        "verses.{$vIndex}.words.{$wIndex}",
                        $surahNumber,
                        $vNum,
                        $wp,
                        $w['line_number'] ?? null
                    ));
                }

                $w['verse_number'] = $vNum;
                $flatWords[] = $w;
            }
        }

        // Traverse flat word sequence for sequential checks
        $lastPage = null;
        $lastLine = null;
        $hasLine15OnPage = []; // maps page => bool
        $seenPageLineCombos = []; // maps page:line => bool
        $lastCombo = null;

        foreach ($flatWords as $idx => $w) {
            $p = $w['page_number'] ?? null;
            $l = isset($w['line_number']) ? (int)$w['line_number'] : null;
            $v = $w['verse_number'] ?? null;
            $pos = $w['position'] ?? null;
            $loc = "word_position_{$pos}";

            if ($p === null) {
                $report->addIssue(new ValidationIssue(
                    'missing_word_page',
                    'error',
                    "Word at position {$pos} (Verse {$v}) is missing page_number.",
                    $loc,
                    $surahNumber,
                    $v,
                    $p,
                    $l
                ));
                continue;
            }

            // Rule 4: Impossible line numbers
            if ($l !== null && ($l < 1 || $l > 15)) {
                $report->addIssue(new ValidationIssue(
                    'impossible_line_number',
                    'error',
                    "Word at position {$pos} (Verse {$v}) has impossible line number: {$l}.",
                    $loc,
                    $surahNumber,
                    $v,
                    $p,
                    $l
                ));
            }

            // Rule 5: Page regressions
            if ($lastPage !== null && $p < $lastPage) {
                $report->addIssue(new ValidationIssue(
                    'page_number_regression',
                    'error',
                    "Page number went backward: from {$lastPage} to {$p} at Verse {$v} position {$pos}.",
                    $loc,
                    $surahNumber,
                    $v,
                    $p,
                    $l
                ));
            }

            // Rule 6: Unexpected page skips
            if ($lastPage !== null && ($p - $lastPage) > 1) {
                $report->addIssue(new ValidationIssue(
                    'unexpected_page_skip',
                    'error',
                    "Page number skipped forward by more than 1 page: from {$lastPage} to {$p} at Verse {$v} position {$pos}.",
                    $loc,
                    $surahNumber,
                    $v,
                    $p,
                    $l
                ));
            }

            if ($l === 15) {
                $hasLine15OnPage[$p] = true;
            }

            // Rule 1: Broken page wrapping (Page X Line 15 followed by Page X Line 1)
            if ($l === 1 && !empty($hasLine15OnPage[$p])) {
                $report->addIssue(new ValidationIssue(
                    'broken_page_wrapping',
                    'error',
                    "Word at position {$pos} (Verse {$v}) is marked on Page {$p} Line 1, but we already saw Line 15 on Page {$p}.",
                    $loc,
                    $surahNumber,
                    $v,
                    $p,
                    $l
                ));
            }

            // Rule 7: Duplicate / cyclic page/line combinations
            if ($l !== null) {
                $combo = "{$p}:{$l}";
                if ($lastCombo !== null && $combo !== $lastCombo) {
                    // We left the previous combo
                    $seenPageLineCombos[$lastCombo] = true;
                }
                if (!empty($seenPageLineCombos[$combo])) {
                    $report->addIssue(new ValidationIssue(
                        'duplicate_pageline_combo_cycle',
                        'error',
                        "Word at position {$pos} (Verse {$v}) returned to previously exited page:line combo {$combo}.",
                        $loc,
                        $surahNumber,
                        $v,
                        $p,
                        $l
                    ));
                }
                $lastCombo = $combo;
            }

            $lastPage = $p;
            $lastLine = $l;
        }

        return $report;
    }
}
