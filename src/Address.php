<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

/**
 * A subdistrict with its district, province and (when known) postal code,
 * shaped for address forms.
 *
 * toArray() uses the field names of Google's libaddressinput and CLDR:
 * administrativeArea is the province, locality the district (amphoe or
 * Bangkok khet), dependentLocality the subdistrict (tambon or Bangkok khwaeng).
 */
final class Address implements \JsonSerializable
{
    public const COUNTRY_CODE = 'TH';

    public readonly string $countryCode;

    public function __construct(
        public readonly Province $province,
        public readonly District $district,
        public readonly Subdistrict $subdistrict,
        public readonly ?string $postalCode,
    ) {
        $this->countryCode = self::COUNTRY_CODE;
    }

    /**
     * @param 'th'|'en' $language
     * @return array{countryCode: string, administrativeArea: string, locality: string, dependentLocality: string, postalCode: string|null}
     */
    public function toArray(string $language = 'th'): array
    {
        $name = match ($language) {
            'th' => static fn (Area $area): string => $area->nameTh,
            'en' => static fn (Area $area): string => $area->nameEn,
            default => throw new \InvalidArgumentException("Language must be 'th' or 'en', not '{$language}'"),
        };

        return [
            'countryCode' => $this->countryCode,
            'administrativeArea' => $name($this->province),
            'locality' => $name($this->district),
            'dependentLocality' => $name($this->subdistrict),
            'postalCode' => $this->postalCode,
        ];
    }

    /** @return array{countryCode: string, administrativeArea: string, locality: string, dependentLocality: string, postalCode: string|null} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
