<?php

namespace App\Modules\Dataset\Validation;

class DatasetMetadata
{
    public string $provider;
    public ?string $providerVersion;
    public string $downloadedAt;
    public string $normalizedAt;
    public string $datasetType;
    public int $surahNumber;
    public ?string $integrityHash;

    public function __construct(
        string $provider,
        ?string $providerVersion,
        string $downloadedAt,
        string $normalizedAt,
        string $datasetType,
        int $surahNumber,
        ?string $integrityHash = null
    ) {
        $this->provider = $provider;
        $this->providerVersion = $providerVersion;
        $this->downloadedAt = $downloadedAt;
        $this->normalizedAt = $normalizedAt;
        $this->datasetType = $datasetType;
        $this->surahNumber = $surahNumber;
        $this->integrityHash = $integrityHash;
    }

    public function withIntegrityHash(string $hash): self
    {
        return new self(
            $this->provider,
            $this->providerVersion,
            $this->downloadedAt,
            $this->normalizedAt,
            $this->datasetType,
            $this->surahNumber,
            $hash
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_version' => $this->providerVersion,
            'downloaded_at' => $this->downloadedAt,
            'normalized_at' => $this->normalizedAt,
            'dataset_type' => $this->datasetType,
            'surah_number' => $this->surahNumber,
            'integrity_hash' => $this->integrityHash,
        ];
    }
}
