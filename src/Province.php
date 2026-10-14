<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

use Illuminate\Support\Collection;

/**
 * A province (จังหวัด), or Bangkok. Its code is DOPA's 2-digit code, e.g. "10";
 * its ISO 3166-2:TH code is e.g. "TH-10".
 */
final class Province extends Model
{
    public function __construct(
        AddressRepository $repository,
        string $code,
        string $nameTh,
        string $nameEn,
        public readonly string $isoCode,
    ) {
        parent::__construct($repository, $code, $nameTh, $nameEn);
    }

    /** @return Collection<int, District> */
    public function districts(): Collection
    {
        return $this->repository->districtsOf($this->code);
    }

    /** @return Collection<int, Subdistrict> every subdistrict of every district */
    public function subdistricts(): Collection
    {
        return $this->districts()->flatMap(fn (District $d) => $d->subdistricts()->all())->values();
    }

    /** @return array{code: string, name_th: string, name_en: string, iso_code: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name_th' => $this->nameTh, 'name_en' => $this->nameEn, 'iso_code' => $this->isoCode];
    }

    protected function relations(): array
    {
        return [];
    }
}
