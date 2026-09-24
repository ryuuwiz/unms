<?php

namespace App\DTO\Storage;

use Livewire\Wireable;

final readonly class S3HealthCheckResult implements Wireable
{
    public function __construct(
        public bool $applicable,
        public string $diskName,
        public ?string $bucket = null,
        public ?bool $bucketOk = null,
        public ?bool $publicUrlOk = null,
        public ?string $errorMessage = null,
        public ?string $checkedAt = null,
    ) {}

    public static function notApplicable(string $diskName): self
    {
        return new self(applicable: false, diskName: $diskName);
    }

    public function isHealthy(): bool
    {
        return $this->applicable && $this->bucketOk === true && $this->publicUrlOk === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toLivewire(): array
    {
        return [
            'applicable' => $this->applicable,
            'diskName' => $this->diskName,
            'bucket' => $this->bucket,
            'bucketOk' => $this->bucketOk,
            'publicUrlOk' => $this->publicUrlOk,
            'errorMessage' => $this->errorMessage,
            'checkedAt' => $this->checkedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromLivewire($value): static
    {
        return new self(
            applicable: (bool) ($value['applicable'] ?? false),
            diskName: (string) ($value['diskName'] ?? ''),
            bucket: $value['bucket'] ?? null,
            bucketOk: $value['bucketOk'] ?? null,
            publicUrlOk: $value['publicUrlOk'] ?? null,
            errorMessage: $value['errorMessage'] ?? null,
            checkedAt: $value['checkedAt'] ?? null,
        );
    }
}
