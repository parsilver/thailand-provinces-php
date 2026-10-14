<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

use Illuminate\Support\Collection;

/**
 * Static access to the provinces, districts and subdistricts of Thailand.
 *
 *   Thailand::province('10')->districts();
 *   Thailand::subdistrict('100101')->district->province->nameTh;
 *
 * Every call goes to one AddressRepository: the one given to swap(), else the
 * one the resolver returns (Laravel's service provider resolves the container's
 * singleton, so a fake bound there reaches this class too), else a default
 * created on first use.
 */
final class Thailand
{
    private static ?AddressRepository $repository = null;

    /** @var (\Closure(): AddressRepository)|null */
    private static ?\Closure $resolver = null;

    public static function repository(): AddressRepository
    {
        if (self::$repository !== null) {
            return self::$repository;
        }

        return self::$resolver !== null ? (self::$resolver)() : self::$repository = new AddressRepository();
    }

    /** Use this repository, e.g. AddressRepository::fromArrays(...) in a test; null goes back to the resolver or the default. */
    public static function swap(?AddressRepository $repository): void
    {
        self::$repository = $repository;
    }

    /** @param (\Closure(): AddressRepository)|null $resolver how to find the repository when none was swapped in */
    public static function resolveUsing(?\Closure $resolver): void
    {
        self::$resolver = $resolver;
        self::$repository = null;
    }

    /** @return Collection<int, Province> */
    public static function provinces(): Collection
    {
        return self::repository()->provinces();
    }

    /** @return Collection<int, District> */
    public static function districts(): Collection
    {
        return self::repository()->districts();
    }

    /** @return Collection<int, Subdistrict> */
    public static function subdistricts(): Collection
    {
        return self::repository()->subdistricts();
    }

    public static function province(int|string|null $code): ?Province
    {
        return self::repository()->province($code);
    }

    public static function district(int|string|null $code): ?District
    {
        return self::repository()->district($code);
    }

    public static function subdistrict(int|string|null $code): ?Subdistrict
    {
        return self::repository()->subdistrict($code);
    }

    /** @throws Exceptions\NotFoundException */
    public static function provinceOrFail(int|string $code): Province
    {
        return self::repository()->provinceOrFail($code);
    }

    /** @throws Exceptions\NotFoundException */
    public static function districtOrFail(int|string $code): District
    {
        return self::repository()->districtOrFail($code);
    }

    /** @throws Exceptions\NotFoundException */
    public static function subdistrictOrFail(int|string $code): Subdistrict
    {
        return self::repository()->subdistrictOrFail($code);
    }

    /** @return Collection<int, Subdistrict> the subdistricts a postal code serves */
    public static function postalCode(int|string $postalCode): Collection
    {
        return self::repository()->postalCode($postalCode);
    }

    /** @return Collection<int, Province|District|Subdistrict> matches by Thai or English name, at every level */
    public static function search(string $query): Collection
    {
        return self::repository()->search($query);
    }
}
