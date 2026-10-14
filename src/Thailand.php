<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

/**
 * Thailand's provinces, districts and subdistricts, with their postcodes.
 *
 * The data is loaded on first use and shared by every instance in the process.
 * It is read-only, so instances are cheap.
 */
final class Thailand
{
    /** @var Collection<Province>|null */
    private static ?Collection $provinces = null;

    /** @var Collection<District>|null */
    private static ?Collection $districts = null;

    /** @var Collection<Subdistrict>|null */
    private static ?Collection $subdistricts = null;

    /** @var array<string, list<string>>|null child codes by parent code */
    private static ?array $children = null;

    /** @var array<string, list<string>>|null subdistrict codes by postcode */
    private static ?array $byPostcode = null;

    /** @return Collection<Province> */
    public function provinces(): Collection
    {
        return self::$provinces ??= self::load('provinces.php', static fn (string $code, array $row): Province => new Province($code, $row[0], $row[1]));
    }

    /** @return Collection<District> */
    public function districts(): Collection
    {
        return self::$districts ??= self::load('districts.php', static fn (string $code, array $row): District => new District($code, $row[0], $row[1], substr($code, 0, 2)));
    }

    /** @return Collection<Subdistrict> */
    public function subdistricts(): Collection
    {
        return self::$subdistricts ??= self::load('subdistricts.php', static fn (string $code, array $row): Subdistrict => new Subdistrict($code, $row[0], $row[1], substr($code, 0, 4), substr($code, 0, 2), $row[2]));
    }

    /** The province with this 2-digit code, or null. */
    public function province(int|string $code): ?Province
    {
        return $this->provinces()->find($code);
    }

    /** The district with this 4-digit code, or null. */
    public function district(int|string $code): ?District
    {
        return $this->districts()->find($code);
    }

    /** The subdistrict with this 6-digit code, or null. */
    public function subdistrict(int|string $code): ?Subdistrict
    {
        return $this->subdistricts()->find($code);
    }

    /**
     * The districts of a province; empty for an unknown province.
     *
     * @return Collection<District>
     */
    public function districtsOf(Province|int|string $province): Collection
    {
        return $this->childrenOf($this->districts(), $province instanceof Province ? $province->code : (string) $province);
    }

    /**
     * The subdistricts of a district; empty for an unknown district.
     *
     * @return Collection<Subdistrict>
     */
    public function subdistrictsOf(District|int|string $district): Collection
    {
        return $this->childrenOf($this->subdistricts(), $district instanceof District ? $district->code : (string) $district);
    }

    /**
     * The subdistricts a postcode serves; empty for an unknown postcode.
     *
     * @return Collection<Subdistrict>
     */
    public function subdistrictsByPostcode(string $postcode): Collection
    {
        if (self::$byPostcode === null) {
            $index = [];
            foreach ($this->subdistricts() as $subdistrict) {
                foreach ($subdistrict->postcodes as $code) {
                    $index[$code][] = $subdistrict->code;
                }
            }
            self::$byPostcode = $index;
        }

        return $this->pick($this->subdistricts(), self::$byPostcode[trim($postcode)] ?? []);
    }

    /**
     * @template T of Area
     * @param Collection<T> $children
     * @return Collection<T>
     */
    private function childrenOf(Collection $children, string $parentCode): Collection
    {
        if (self::$children === null) {
            $index = [];
            foreach ([$this->districts(), $this->subdistricts()] as $level) {
                foreach ($level as $area) {
                    $index[substr($area->code, 0, -2)][] = $area->code;
                }
            }
            self::$children = $index;
        }

        return $this->pick($children, self::$children[$parentCode] ?? []);
    }

    /**
     * @template T of Area
     * @param Collection<T> $from
     * @param list<string> $codes
     * @return Collection<T>
     */
    private function pick(Collection $from, array $codes): Collection
    {
        $items = [];
        foreach ($codes as $code) {
            $area = $from->find($code);
            if ($area !== null) {
                $items[$code] = $area;
            }
        }

        return new Collection($items);
    }

    /**
     * @template T of Area
     * @param callable(string, array<int, mixed>): T $make
     * @return Collection<T>
     */
    private static function load(string $file, callable $make): Collection
    {
        /** @var array<array-key, array<int, mixed>> $rows */
        $rows = require dirname(__DIR__) . '/data/' . $file;
        $items = [];
        foreach ($rows as $code => $row) {
            $items[$code] = $make((string) $code, $row);
        }

        return new Collection($items);
    }
}
