<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Rules;

/**
 * The value is a Thai postal code that serves at least one subdistrict, and with
 * forSubdistrict() it serves the subdistrict given in another field. A
 * subdistrict with no known postal code accepts any valid one.
 * String rule: thai_postal_code or thai_postal_code:subdistrict_field.
 */
final class ThaiPostalCode extends AreaRule
{
    public function forSubdistrict(string $field): static
    {
        $this->parentField = $field;

        return $this;
    }

    public function failure(mixed $value, array $data, string $attribute = ''): ?string
    {
        $postalCode = self::code($value);
        if ($postalCode === null || strlen($postalCode) !== 5 || $this->repository()->postalCode($postalCode)->isEmpty()) {
            return 'postal_code';
        }
        $parent = $this->parentValue($data, $attribute, $unresolved);
        if ($unresolved) {
            return 'postal_code_for_subdistrict';
        }
        $subdistrict = $this->repository()->subdistrict(self::code($parent));
        if ($subdistrict === null || $subdistrict->postalCodes === []) {
            return null;
        }

        return in_array($postalCode, $subdistrict->postalCodes, true) ? null : 'postal_code_for_subdistrict';
    }
}
