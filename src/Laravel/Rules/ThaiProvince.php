<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Rules;

/** The value is a province's DOPA code, e.g. "10". String rule: thai_province. */
final class ThaiProvince extends AreaRule
{
    public function failure(mixed $value, array $data, string $attribute = ''): ?string
    {
        return $this->repository()->province(self::code($value)) === null ? 'province' : null;
    }
}
