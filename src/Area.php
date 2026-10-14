<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

/**
 * What every administrative area has: its DOPA code and its Thai and English
 * names, exactly as DOPA publishes them.
 */
abstract class Area implements \JsonSerializable
{
    public function __construct(
        public readonly string $code,
        public readonly string $nameTh,
        public readonly string $nameEn,
    ) {
    }

    /** @return array<string, string|list<string>> */
    abstract public function toArray(): array;

    /** @return array<string, string|list<string>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
