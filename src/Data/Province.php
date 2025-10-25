<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Data;

final readonly class Province
{
    public function __construct(
        public string $name,
        public string $thaiName,
        public string $resourceId,
    ) {}

    public function getJsonFilename(): string
    {
        return $this->name.'.json';
    }

    public function getDownloadUrl(): string
    {
        return sprintf(
            'https://catalog.dopa.go.th/dataset/705402d4-2715-4ece-9166-22b54c6c3476/resource/%s/download/.json',
            $this->resourceId
        );
    }
}
