<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Casts;

use Farzai\ThailandAddress\Subdistrict;

/**
 * 'column' => AsSubdistrict::class: stores the subdistrict's DOPA code, reads a Subdistrict.
 *
 * @extends AreaCast<Subdistrict>
 */
final class AsSubdistrict extends AreaCast
{
    protected function level(): string
    {
        return 'subdistrict';
    }

    protected function modelClass(): string
    {
        return Subdistrict::class;
    }
}
