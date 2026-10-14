<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

/**
 * A subdistrict: a tambon (ตำบล), or a khwaeng (แขวง) in Bangkok. Its code is
 * DOPA's 6-digit code, e.g. "100101", whose first four digits are the district's.
 *
 * A subdistrict can have more than one postal code, and one postal code can
 * serve many subdistricts. The list is empty where no source covers it.
 *
 * @property-read District $district
 * @property-read Province $province
 */
final class Subdistrict extends Model
{
    /** @param list<string> $postalCodes */
    public function __construct(
        AddressRepository $repository,
        string $code,
        string $nameTh,
        string $nameEn,
        public readonly string $districtCode,
        public readonly string $provinceCode,
        public readonly array $postalCodes,
    ) {
        parent::__construct($repository, $code, $nameTh, $nameEn);
    }

    public function district(): District
    {
        return $this->repository->districtOrFail($this->districtCode);
    }

    public function province(): Province
    {
        return $this->repository->provinceOrFail($this->provinceCode);
    }

    /**
     * This subdistrict as a full address. Without $postalCode, the postal code
     * is filled only when the subdistrict has exactly one; none is guessed
     * among several.
     *
     * @param 'th'|'en' $locale
     * @throws \InvalidArgumentException when $postalCode does not serve this subdistrict
     */
    public function toAddress(?string $postalCode = null, string $locale = 'th'): Address
    {
        if ($postalCode !== null && !in_array($postalCode, $this->postalCodes, true)) {
            throw new \InvalidArgumentException("Postal code {$postalCode} does not serve subdistrict {$this->code}");
        }

        return new Address(
            $this->province(),
            $this->district(),
            $this,
            $postalCode ?? (count($this->postalCodes) === 1 ? $this->postalCodes[0] : null),
            $locale,
        );
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

    protected function relations(): array
    {
        return ['district', 'province'];
    }

    protected static function lookup(AddressRepository $repository, string $code): static
    {
        return $repository->subdistrictOrFail($code);
    }

    protected function restoreFrom(Model $source): void
    {
        assert($source instanceof self);
        $this->districtCode = $source->districtCode;
        $this->provinceCode = $source->provinceCode;
        $this->postalCodes = $source->postalCodes;
    }
}
