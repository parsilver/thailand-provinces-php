<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Rules;

/**
 * The value is a district's DOPA code, e.g. "1001", and with inProvince() the
 * district lies in the province given in another field.
 * String rule: thai_district or thai_district:province_field.
 */
final class ThaiDistrict extends AreaRule
{
    public function inProvince(string $field): static
    {
        $this->parentField = $field;

        return $this;
    }

    public function failure(mixed $value, array $data, string $attribute = ''): ?string
    {
        $district = $this->repository()->district(self::code($value));
        if ($district === null) {
            return 'district';
        }
        $province = $this->repository()->province(self::code($this->parentValue($data, $attribute)));

        return $province !== null && $province->code !== $district->provinceCode ? 'district_in_province' : null;
    }
}
