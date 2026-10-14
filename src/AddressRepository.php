<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

use Farzai\ThailandAddress\Exceptions\NotFoundException;
use Illuminate\Support\Collection;

/**
 * Every province, district and subdistrict, with lookups by code, by parent,
 * by postal code and by name. Thailand is a static facade over one instance;
 * in Laravel the container holds that instance as a singleton.
 *
 * Each level's data file is read on first use of that level, once per
 * process, so a province lookup never loads the 7,436 subdistricts. Models are
 * built once per repository.
 */
class AddressRepository
{
    /** @var array<string, array<string, array<array-key, array<string, mixed>>>> raw rows by data file path */
    private static array $files = [];

    /** @var array<string, array<array-key, array<string, mixed>>> raw rows by level, filled per level on first use */
    private array $rows = [];

    /** @var array<string, Province>|null */
    private ?array $provinces = null;

    /** @var array<string, District>|null */
    private ?array $districts = null;

    /** @var array<string, Subdistrict>|null */
    private ?array $subdistricts = null;

    /** @var array<string, array<string, list<string>>> child codes by parent code, per child level */
    private array $children = [];

    /** @var array<string, list<string>>|null subdistrict codes by postal code */
    private ?array $byPostalCode = null;

    public function __construct(private readonly ?string $dataPath = null)
    {
    }

    /**
     * A repository over the given rows instead of the shipped data, for tests
     * and fakes. The rows have the shape of the files in data/, keyed by code.
     *
     * @param array<array-key, array{name_th: string, name_en: string, iso_code: string}> $provinces
     * @param array<array-key, array{name_th: string, name_en: string}> $districts
     * @param array<array-key, array{name_th: string, name_en: string, postal_codes: list<string>}> $subdistricts
     */
    public static function fromArrays(array $provinces, array $districts = [], array $subdistricts = []): static
    {
        $repository = new static();
        $repository->rows = ['provinces' => $provinces, 'districts' => $districts, 'subdistricts' => $subdistricts];

        return $repository;
    }

    /** @return Collection<int, Province> */
    public function provinces(): Collection
    {
        return new Collection(array_values($this->provinceIndex()));
    }

    /** @return Collection<int, District> */
    public function districts(): Collection
    {
        return new Collection(array_values($this->districtIndex()));
    }

    /** @return Collection<int, Subdistrict> */
    public function subdistricts(): Collection
    {
        return new Collection(array_values($this->subdistrictIndex()));
    }

    /** The province with this 2-digit DOPA code, or null. */
    public function province(int|string|null $code): ?Province
    {
        return $code === null ? null : ($this->provinceIndex()[(string) $code] ?? null);
    }

    /** The district with this 4-digit DOPA code, or null. */
    public function district(int|string|null $code): ?District
    {
        return $code === null ? null : ($this->districtIndex()[(string) $code] ?? null);
    }

    /** The subdistrict with this 6-digit DOPA code, or null. */
    public function subdistrict(int|string|null $code): ?Subdistrict
    {
        return $code === null ? null : ($this->subdistrictIndex()[(string) $code] ?? null);
    }

    /** @throws NotFoundException */
    public function provinceOrFail(int|string $code): Province
    {
        return $this->province($code) ?? throw NotFoundException::for('province', $code);
    }

    /** @throws NotFoundException */
    public function districtOrFail(int|string $code): District
    {
        return $this->district($code) ?? throw NotFoundException::for('district', $code);
    }

    /** @throws NotFoundException */
    public function subdistrictOrFail(int|string $code): Subdistrict
    {
        return $this->subdistrict($code) ?? throw NotFoundException::for('subdistrict', $code);
    }

    /** @return Collection<int, District> the districts of a province; empty for an unknown one */
    public function districtsOf(Province|int|string $province): Collection
    {
        $code = $province instanceof Province ? $province->code : (string) $province;

        return $this->pick($this->districtIndex(), $this->childIndex('districts')[$code] ?? []);
    }

    /** @return Collection<int, Subdistrict> the subdistricts of a district; empty for an unknown one */
    public function subdistrictsOf(District|int|string $district): Collection
    {
        $code = $district instanceof District ? $district->code : (string) $district;

        return $this->pick($this->subdistrictIndex(), $this->childIndex('subdistricts')[$code] ?? []);
    }

    /** @return Collection<int, Subdistrict> the subdistricts a postal code serves; empty for an unknown one */
    public function postalCode(int|string $postalCode): Collection
    {
        if ($this->byPostalCode === null) {
            $index = [];
            foreach ($this->subdistrictIndex() as $subdistrict) {
                foreach ($subdistrict->postalCodes as $code) {
                    $index[$code][] = $subdistrict->code;
                }
            }
            $this->byPostalCode = $index;
        }

        return $this->pick($this->subdistrictIndex(), $this->byPostalCode[trim((string) $postalCode)] ?? []);
    }

    /**
     * Provinces, then districts, then subdistricts whose Thai or English name
     * contains $query, ignoring case and surrounding whitespace. A blank query
     * matches nothing.
     *
     * @return Collection<int, Province|District|Subdistrict>
     */
    public function search(string $query): Collection
    {
        $needle = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));
        if ($needle === '') {
            return new Collection();
        }
        $matches = static fn (Model $m): bool => str_contains(mb_strtolower($m->nameTh), $needle)
            || str_contains(mb_strtolower($m->nameEn), $needle);

        return $this->provinces()->filter($matches)
            ->concat($this->districts()->filter($matches))
            ->concat($this->subdistricts()->filter($matches))
            ->values();
    }

    /**
     * @template T of Model
     * @param array<string, T> $index
     * @param list<string> $codes
     * @return Collection<int, T>
     */
    private function pick(array $index, array $codes): Collection
    {
        $items = [];
        foreach ($codes as $code) {
            if (isset($index[$code])) {
                $items[] = $index[$code];
            }
        }

        return new Collection($items);
    }

    /** @return array<string, Province> */
    private function provinceIndex(): array
    {
        if ($this->provinces === null) {
            $this->provinces = [];
            foreach ($this->rows('provinces') as $code => $row) {
                $code = (string) $code;
                $this->provinces[$code] = new Province($this, $code, $row['name_th'], $row['name_en'], $row['iso_code']);
            }
        }

        return $this->provinces;
    }

    /** @return array<string, District> */
    private function districtIndex(): array
    {
        if ($this->districts === null) {
            $this->districts = [];
            foreach ($this->rows('districts') as $code => $row) {
                $code = (string) $code;
                $this->districts[$code] = new District($this, $code, $row['name_th'], $row['name_en'], substr($code, 0, 2));
            }
        }

        return $this->districts;
    }

    /** @return array<string, Subdistrict> */
    private function subdistrictIndex(): array
    {
        if ($this->subdistricts === null) {
            $this->subdistricts = [];
            foreach ($this->rows('subdistricts') as $code => $row) {
                $code = (string) $code;
                $this->subdistricts[$code] = new Subdistrict($this, $code, $row['name_th'], $row['name_en'], substr($code, 0, 4), substr($code, 0, 2), $row['postal_codes']);
            }
        }

        return $this->subdistricts;
    }

    /**
     * Child codes by parent code for one child level: a code's parent is the
     * code without its last two digits.
     *
     * @param 'districts'|'subdistricts' $level
     * @return array<string, list<string>>
     */
    private function childIndex(string $level): array
    {
        if (!isset($this->children[$level])) {
            $index = [];
            foreach (array_keys($this->rows($level)) as $code) {
                $code = (string) $code;
                $index[substr($code, 0, -2)][] = $code;
            }
            $this->children[$level] = $index;
        }

        return $this->children[$level];
    }

    /**
     * One level's raw rows, read from its data file on first use.
     *
     * @param 'provinces'|'districts'|'subdistricts' $level
     * @return array<array-key, array<string, mixed>>
     */
    private function rows(string $level): array
    {
        if (!isset($this->rows[$level])) {
            $file = ($this->dataPath ?? dirname(__DIR__) . '/data') . '/' . $level . '.php';
            $this->rows[$level] = self::$files[$file] ??= require $file;
        }

        return $this->rows[$level];
    }
}
