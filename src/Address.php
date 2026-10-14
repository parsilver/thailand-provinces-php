<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A subdistrict with its district, province and (when known) postal code,
 * shaped for address forms, in one locale.
 *
 * The field names are Google libaddressinput's and CLDR's: administrativeArea
 * is the province, locality the district (amphoe or Bangkok khet),
 * dependentLocality the subdistrict (tambon or Bangkok khwaeng).
 *
 * @implements Arrayable<string, string|null>
 */
final class Address implements Arrayable, \JsonSerializable
{
    public const COUNTRY_CODE = 'TH';

    public readonly string $countryCode;

    public readonly string $administrativeArea;

    public readonly string $locality;

    public readonly string $dependentLocality;

    /** @param 'th'|'en' $locale */
    public function __construct(
        public readonly Province $province,
        public readonly District $district,
        public readonly Subdistrict $subdistrict,
        public readonly ?string $postalCode,
        public readonly string $locale = 'th',
    ) {
        $this->countryCode = self::COUNTRY_CODE;
        $this->administrativeArea = $province->name($locale);
        $this->locality = $district->name($locale);
        $this->dependentLocality = $subdistrict->name($locale);
    }

    /** The same address in another locale. */
    public function inLocale(string $locale): self
    {
        return new self($this->province, $this->district, $this->subdistrict, $this->postalCode, $locale);
    }

    /** @return array{countryCode: string, administrativeArea: string, locality: string, dependentLocality: string, postalCode: string|null} */
    public function toArray(): array
    {
        return [
            'countryCode' => $this->countryCode,
            'administrativeArea' => $this->administrativeArea,
            'locality' => $this->locality,
            'dependentLocality' => $this->dependentLocality,
            'postalCode' => $this->postalCode,
        ];
    }

    /** @return array{countryCode: string, administrativeArea: string, locality: string, dependentLocality: string, postalCode: string|null} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
