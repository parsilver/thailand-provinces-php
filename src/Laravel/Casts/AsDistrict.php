<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Casts;

use Farzai\ThailandAddress\District;

/**
 * 'column' => AsDistrict::class: stores the district's DOPA code, reads a District.
 *
 * @extends AreaCast<District>
 */
final class AsDistrict extends AreaCast
{
    protected function level(): string
    {
        return 'district';
    }

    protected function modelClass(): string
    {
        return District::class;
    }
}
