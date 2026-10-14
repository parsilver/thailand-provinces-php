<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Rules;

/**
 * The value is a subdistrict's DOPA code, e.g. "100101", and with inDistrict()
 * the subdistrict lies in the district given in another field.
 * String rule: thai_subdistrict or thai_subdistrict:district_field.
 */
final class ThaiSubdistrict extends AreaRule
{
    public function inDistrict(string $field): static
    {
        $this->parentField = $field;

        return $this;
    }

    public function failure(mixed $value, array $data, string $attribute = ''): ?string
    {
        $subdistrict = $this->repository()->subdistrict(self::code($value));
        if ($subdistrict === null) {
            return 'subdistrict';
        }
        $parent = $this->parentValue($data, $attribute, $unresolved);
        if ($unresolved) {
            return 'subdistrict_in_district';
        }
        $district = $this->repository()->district(self::code($parent));

        return $district !== null && $district->code !== $subdistrict->districtCode ? 'subdistrict_in_district' : null;
    }
}
