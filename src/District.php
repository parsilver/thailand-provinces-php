<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

use Illuminate\Support\Collection;

/**
 * A district: an amphoe (อำเภอ), or a khet (เขต) in Bangkok. Its code is DOPA's
 * 4-digit code, e.g. "1001", whose first two digits are the province's.
 *
 * @property-read Province $province
 */
final class District extends Model
{
    public function __construct(
        AddressRepository $repository,
        string $code,
        string $nameTh,
        string $nameEn,
        public readonly string $provinceCode,
    ) {
        parent::__construct($repository, $code, $nameTh, $nameEn);
    }

    public function province(): Province
    {
        return $this->repository->provinceOrFail($this->provinceCode);
    }

    /** @return Collection<int, Subdistrict> */
    public function subdistricts(): Collection
    {
        return $this->repository->subdistrictsOf($this->code);
    }

    /** @return array{code: string, name_th: string, name_en: string, province_code: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name_th' => $this->nameTh, 'name_en' => $this->nameEn, 'province_code' => $this->provinceCode];
    }

    protected function relations(): array
    {
        return ['province'];
    }

    protected static function lookup(AddressRepository $repository, string $code): static
    {
        return $repository->districtOrFail($code);
    }

    protected function restoreFrom(Model $source): void
    {
        assert($source instanceof self);
        $this->provinceCode = $source->provinceCode;
    }
}
