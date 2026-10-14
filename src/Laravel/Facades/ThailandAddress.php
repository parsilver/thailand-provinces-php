<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Facades;

use Farzai\ThailandAddress\AddressRepository;
use Illuminate\Support\Facades\Facade;

/**
 * The container's AddressRepository; same calls as Farzai\ThailandAddress\Thailand.
 *
 * @method static \Illuminate\Support\Collection<int, \Farzai\ThailandAddress\Province> provinces()
 * @method static \Illuminate\Support\Collection<int, \Farzai\ThailandAddress\District> districts()
 * @method static \Illuminate\Support\Collection<int, \Farzai\ThailandAddress\Subdistrict> subdistricts()
 * @method static \Farzai\ThailandAddress\Province|null province(int|string|null $code)
 * @method static \Farzai\ThailandAddress\District|null district(int|string|null $code)
 * @method static \Farzai\ThailandAddress\Subdistrict|null subdistrict(int|string|null $code)
 * @method static \Farzai\ThailandAddress\Province provinceOrFail(int|string $code)
 * @method static \Farzai\ThailandAddress\District districtOrFail(int|string $code)
 * @method static \Farzai\ThailandAddress\Subdistrict subdistrictOrFail(int|string $code)
 * @method static \Illuminate\Support\Collection<int, \Farzai\ThailandAddress\Subdistrict> postalCode(int|string $postalCode)
 * @method static \Illuminate\Support\Collection<int, \Farzai\ThailandAddress\Province|\Farzai\ThailandAddress\District|\Farzai\ThailandAddress\Subdistrict> search(string $query)
 *
 * @see AddressRepository
 */
final class ThailandAddress extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AddressRepository::class;
    }
}
