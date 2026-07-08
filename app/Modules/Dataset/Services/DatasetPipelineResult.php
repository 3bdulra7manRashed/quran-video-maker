<?php

namespace App\Modules\Dataset\Services;

use App\Modules\Dataset\Validation\DatasetMetadata;

class DatasetPipelineResult
{
    public array $dataset;
    public DatasetMetadata $metadata;

    public function __construct(array $dataset, DatasetMetadata $metadata)
    {
        $this->dataset = $dataset;
        $this->metadata = $metadata;
    }
}
