<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Casts;

use Farzai\ThailandAddress\Province;

/**
 * 'column' => AsProvince::class: stores the province's DOPA code, reads a Province.
 *
 * @extends AreaCast<Province>
 */
final class AsProvince extends AreaCast
{
    protected function level(): string
    {
        return 'province';
    }

    protected function modelClass(): string
    {
        return Province::class;
    }
}
