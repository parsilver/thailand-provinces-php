<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

/**
 * A province (จังหวัด), or Bangkok. Its code is DOPA's 2-digit code, e.g. "10";
 * its ISO 3166-2:TH code is e.g. "TH-10".
 */
final class Province extends Area
{
    public function __construct(
        string $code,
        string $nameTh,
        string $nameEn,
        public readonly string $isoCode,
    ) {
        parent::__construct($code, $nameTh, $nameEn);
    }

    /** @return array{code: string, name_th: string, name_en: string, iso_code: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name_th' => $this->nameTh, 'name_en' => $this->nameEn, 'iso_code' => $this->isoCode];
    }
}
