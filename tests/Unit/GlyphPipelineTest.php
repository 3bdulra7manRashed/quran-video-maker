<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\Dataset\Validation\GlyphDatasetValidator;
use App\Modules\Dataset\Validation\DatasetNormalizer;
use App\Modules\Dataset\Validation\GlyphCorrectionRegistry;
use App\Modules\Dataset\Validation\Rules\PageWrapCorrectionRule;
use App\Modules\Dataset\Services\DatasetPipeline;
use App\Modules\Dataset\Validation\ValidationReport;
use App\Modules\Dataset\Validation\Rules\GlyphCorrectionRule;
use App\Modules\Dataset\Validation\CorrectionReport;
use Illuminate\Support\Facades\File;

class GlyphPipelineTest extends TestCase
{
    protected function getCleanDataset(): array
    {
        return [
            'verses' => [
                [
                    'verse_key' => '55:1',
                    'verse_number' => 1,
                    'page_number' => 500,
                    'words' => [
                        ['position' => 1, 'page_number' => 500, 'line_number' => 1, 'code_v1' => 'A'],
                        ['position' => 2, 'page_number' => 500, 'line_number' => 1, 'code_v1' => 'B'],
                    ]
                ],
                [
                    'verse_key' => '55:2',
                    'verse_number' => 2,
                    'page_number' => 500,
                    'words' => [
                        ['position' => 1, 'page_number' => 500, 'line_number' => 2, 'code_v1' => 'C'],
                    ]
                ]
            ]
        ];
    }

    protected function getCorruptedDataset(): array
    {
        return [
            'verses' => [
                [
                    'verse_key' => '55:16',
                    'verse_number' => 16,
                    'page_number' => 531,
                    'words' => [
                        ['position' => 1, 'page_number' => 531, 'line_number' => 15, 'code_v1' => 'A'],
                    ]
                ],
                [
                    'verse_key' => '55:17',
                    'verse_number' => 17,
                    'page_number' => 531, // Corrupted: should be 532
                    'words' => [
                        ['position' => 1, 'page_number' => 531, 'line_number' => 1, 'code_v1' => 'B'],
                    ]
                ],
                [
                    'verse_key' => '55:18',
                    'verse_number' => 18,
                    'page_number' => 531, // Corrupted: should be 532
                    'words' => [
                        ['position' => 1, 'page_number' => 531, 'line_number' => 1, 'code_v1' => 'C'],
                    ]
                ]
            ]
        ];
    }

    public function test_clean_dataset_passes_validation(): void
    {
        $validator = new GlyphDatasetValidator();
        $report = $validator->validate(55, $this->getCleanDataset());

        $this->assertTrue($report->isValid());
        $this->assertCount(0, $report->getIssues());
    }

    public function test_corrupted_dataset_fails_validation(): void
    {
        $validator = new GlyphDatasetValidator();
        $report = $validator->validate(55, $this->getCorruptedDataset());

        $this->assertFalse($report->isValid());
        
        $issues = $report->getIssues();
        $this->assertGreaterThan(0, count($issues));
        
        // Assert we got broken_page_wrapping issue
        $identifiers = array_map(fn($issue) => $issue->identifier, $issues);
        $this->assertContains('broken_page_wrapping', $identifiers);
    }

    public function test_page_wrap_correction_rule_patches_corrupted_dataset(): void
    {
        $config = [
            55 => [
                17 => ['page_number' => 532],
                18 => ['page_number' => 532]
            ]
        ];

        $rule = new PageWrapCorrectionRule($config);
        $dataset = $this->getCorruptedDataset();

        $this->assertTrue($rule->validate($dataset));

        $reports = $rule->apply($dataset);
        $this->assertCount(2, $reports);
        $this->assertEquals('page_wrap_correction_rule', $reports[0]->ruleIdentifier);
        $this->assertEquals(17, $reports[0]->verseNumber);
        $this->assertEquals(531, $reports[0]->oldValue);
        $this->assertEquals(532, $reports[0]->newValue);

        // Verify it was corrected in the dataset array
        $this->assertEquals(532, $dataset['verses'][1]['page_number']);
        $this->assertEquals(532, $dataset['verses'][1]['words'][0]['page_number']);
        $this->assertEquals(532, $dataset['verses'][2]['page_number']);
        $this->assertEquals(532, $dataset['verses'][2]['words'][0]['page_number']);

        // Now validate patched dataset
        $validator = new GlyphDatasetValidator();
        $report = $validator->validate(55, $dataset);
        $this->assertTrue($report->isValid());
    }

    public function test_pipeline_normalization_is_idempotent(): void
    {
        $config = [
            55 => [
                17 => ['page_number' => 532],
                18 => ['page_number' => 532]
            ]
        ];

        $registry = new GlyphCorrectionRegistry();
        $registry->register(new PageWrapCorrectionRule($config));

        $validator = new GlyphDatasetValidator();
        $normalizer = new DatasetNormalizer($registry);
        $pipeline = new DatasetPipeline($validator, $normalizer);

        $dataset = $this->getCorruptedDataset();

        $metadata = new \App\Modules\Dataset\Validation\DatasetMetadata(
            'quran_com',
            'v4',
            '2026-07-08T12:00:00Z',
            '2026-07-08T12:00:00Z',
            'glyphs',
            55
        );

        // Pass 1: Should apply corrections and succeed
        $cleanedDataset1 = $pipeline->process(55, $dataset, $metadata);

        // Pass 2: Should find it clean, apply 0 corrections, and succeed with identical dataset
        $cleanedDataset2 = $pipeline->process(55, $cleanedDataset1, $metadata);

        $this->assertEquals($cleanedDataset1, $cleanedDataset2);
    }

    public function test_pipeline_throws_exception_if_unresolvable_errors(): void
    {
        // Dataset with page regression (P500 -> P490) which has no normalization rule
        $badDataset = [
            'verses' => [
                [
                    'verse_number' => 1,
                    'page_number' => 500,
                    'words' => [
                        ['position' => 1, 'page_number' => 500, 'line_number' => 1, 'code_v1' => 'A'],
                    ]
                ],
                [
                    'verse_number' => 2,
                    'page_number' => 490, // Regression
                    'words' => [
                        ['position' => 1, 'page_number' => 490, 'line_number' => 2, 'code_v1' => 'B'],
                    ]
                ]
            ]
        ];

        $registry = new GlyphCorrectionRegistry();
        $validator = new GlyphDatasetValidator();
        $normalizer = new DatasetNormalizer($registry);
        $pipeline = new DatasetPipeline($validator, $normalizer);

        $logPath = storage_path('logs/dataset_validation_fail_surah_55.json');
        if (File::exists($logPath)) {
            File::delete($logPath);
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dataset validation failed for Surah 55 even after normalization.');

        try {
            $pipeline->process(55, $badDataset);
        } finally {
            // Verify failure report is saved
            $this->assertTrue(File::exists($logPath));
            $json = json_decode(File::get($logPath), true);
            $this->assertEquals(55, $json['surah']);
            $this->assertNotEmpty($json['re_validation_issues']);
            $this->assertEquals('verse_page_regression', $json['re_validation_issues'][0]['identifier']);
            
            // Cleanup
            File::delete($logPath);
        }
    }

    public function test_rules_run_in_priority_order(): void
    {
        $ruleLowPriority = new class implements GlyphCorrectionRule {
            public function getIdentifier(): string { return 'low'; }
            public function getDescription(): string { return ''; }
            public function priority(): int { return 200; }
            public function supportsSurah(int $surahNumber): bool { return true; }
            public function validate(array $dataset): bool { return true; }
            public function apply(array &$dataset): array {
                $dataset['order'][] = 'low';
                return [];
            }
        };

        $ruleHighPriority = new class implements GlyphCorrectionRule {
            public function getIdentifier(): string { return 'high'; }
            public function getDescription(): string { return ''; }
            public function priority(): int { return 10; }
            public function supportsSurah(int $surahNumber): bool { return true; }
            public function validate(array $dataset): bool { return true; }
            public function apply(array &$dataset): array {
                $dataset['order'][] = 'high';
                return [];
            }
        };

        $registry = new GlyphCorrectionRegistry();
        $registry->register($ruleLowPriority);
        $registry->register($ruleHighPriority);

        $rules = $registry->getRulesForSurah(55);
        $this->assertEquals('high', $rules[0]->getIdentifier());
        $this->assertEquals('low', $rules[1]->getIdentifier());

        $dataset = ['order' => []];
        $normalizer = new DatasetNormalizer($registry);
        $normalizer->normalize(55, $dataset);

        $this->assertEquals(['high', 'low'], $dataset['order']);
    }

    public function test_pipeline_produces_canonical_golden_dataset(): void
    {
        $config = [
            'page_wraps' => [
                55 => [
                    17 => ['page_number' => 532],
                    18 => ['page_number' => 532]
                ]
            ]
        ];

        $registry = new GlyphCorrectionRegistry();
        $registry->register(new PageWrapCorrectionRule($config['page_wraps']));

        $validator = new GlyphDatasetValidator();
        $normalizer = new DatasetNormalizer($registry);
        $pipeline = new DatasetPipeline($validator, $normalizer);

        $dataset = $this->getCorruptedDataset();

        $metadata = new \App\Modules\Dataset\Validation\DatasetMetadata(
            'quran_com',
            'v4',
            '2026-07-08T12:00:00Z',
            '2026-07-08T12:00:00Z',
            'glyphs',
            55
        );

        $output = $pipeline->process(55, $dataset, $metadata);

        // Verification of canonical values
        $this->assertArrayHasKey('metadata', $output);
        $this->assertArrayHasKey('verses', $output);

        // Verify metadata fields
        $this->assertEquals('quran_com', $output['metadata']['provider']);
        $this->assertEquals('v4', $output['metadata']['provider_version']);
        $this->assertEquals('glyphs', $output['metadata']['dataset_type']);
        $this->assertEquals(55, $output['metadata']['surah_number']);
        
        // Integrity hash must be computed
        $this->assertNotNull($output['metadata']['integrity_hash']);
        $this->assertEquals(64, strlen($output['metadata']['integrity_hash'])); // SHA-256 is 64 hex chars

        // Verify expected corrected pages
        $this->assertEquals(532, $output['verses'][1]['page_number']);
        $this->assertEquals(532, $output['verses'][1]['words'][0]['page_number']);
        $this->assertEquals(532, $output['verses'][2]['page_number']);
        $this->assertEquals(532, $output['verses'][2]['words'][0]['page_number']);

        // Verify idempotency of the computed hash
        $expectedHash = hash('sha256', json_encode($output['verses'], JSON_UNESCAPED_UNICODE));
        $this->assertEquals($expectedHash, $output['metadata']['integrity_hash']);
    }
}
