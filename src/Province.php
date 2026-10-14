<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

/** A province (จังหวัด), or Bangkok. Its code is DOPA's 2-digit code, e.g. "10". */
final class Province extends Area
{
    /** @return array{code: string, name_th: string, name_en: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name_th' => $this->nameTh, 'name_en' => $this->nameEn];
    }
}
