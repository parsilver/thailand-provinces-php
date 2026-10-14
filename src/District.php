<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

/**
 * A district: an amphoe (อำเภอ), or a khet (เขต) in Bangkok. Its code is DOPA's
 * 4-digit code, e.g. "1001", whose first two digits are the province's.
 */
final class District extends Area
{
    public function __construct(
        string $code,
        string $nameTh,
        string $nameEn,
        public readonly string $provinceCode,
    ) {
        parent::__construct($code, $nameTh, $nameEn);
    }

    /** @return array{code: string, name_th: string, name_en: string, province_code: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name_th' => $this->nameTh, 'name_en' => $this->nameEn, 'province_code' => $this->provinceCode];
    }
}
