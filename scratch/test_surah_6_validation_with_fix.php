<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Dataset\Validation\GlyphDatasetValidator;

$destFile = __DIR__ . "/surah_6_raw_downloaded.json";
$allVerses = json_decode(file_get_contents($destFile), true);

$dataset = ['verses' => $allVerses];

// First run validation on raw dataset
$validator = new GlyphDatasetValidator();
$report = $validator->validate(6, $dataset);
echo "Raw dataset validation: " . ($report->isValid() ? "PASSED" : "FAILED") . "\n";
echo "Number of issues: " . count($report->getIssues()) . "\n";

// Apply correction: change Verse 131 to Page 144
foreach ($dataset['verses'] as &$verse) {
    if ($verse['verse_number'] === 131) {
        $verse['page_number'] = 144;
        foreach ($verse['words'] as &$word) {
            $word['page_number'] = 144;
        }
    }
}
unset($verse);

// Run validation on corrected dataset
$reportCorrected = $validator->validate(6, $dataset);
echo "Corrected dataset validation: " . ($reportCorrected->isValid() ? "PASSED" : "FAILED") . "\n";
echo "Number of issues after correction: " . count($reportCorrected->getIssues()) . "\n";
if (!$reportCorrected->isValid()) {
    foreach ($reportCorrected->getIssues() as $issue) {
        echo " - " . $issue->message . "\n";
    }
}
