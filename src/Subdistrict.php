<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

/**
 * A subdistrict: a tambon (ตำบล), or a khwaeng (แขวง) in Bangkok. Its code is
 * DOPA's 6-digit code, e.g. "100101", whose first four digits are the district's.
 *
 * A subdistrict can have more than one postal code, and one postal code can
 * serve many subdistricts. The list is empty where no source covers it.
 */
final class Subdistrict extends Area
{
    /** @param list<string> $postalCodes */
    public function __construct(
        string $code,
        string $nameTh,
        string $nameEn,
        public readonly string $districtCode,
        public readonly string $provinceCode,
        public readonly array $postalCodes,
    ) {
        parent::__construct($code, $nameTh, $nameEn);
    }

    /** @return array{code: string, name_th: string, name_en: string, district_code: string, province_code: string, postal_codes: list<string>} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name_th' => $this->nameTh,
            'name_en' => $this->nameEn,
            'district_code' => $this->districtCode,
            'province_code' => $this->provinceCode,
            'postal_codes' => $this->postalCodes,
        ];
    }
}
