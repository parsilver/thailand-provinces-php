# Thai address data for PHP and Laravel

[![tests](https://github.com/parsilver/thailand-provinces-php/actions/workflows/tests.yml/badge.svg)](https://github.com/parsilver/thailand-provinces-php/actions/workflows/tests.yml)

ข้อมูลจังหวัด อำเภอ/เขต ตำบล/แขวง และรหัสไปรษณีย์ของประเทศไทย จากแหล่งข้อมูลทางการ
(กรมการปกครอง, ไปรษณีย์ไทย, กระทรวงดิจิทัลฯ) ใช้กับ Laravel ได้ทันที หรือใช้กับ PHP ธรรมดาก็ได้

Thailand's 77 provinces, 928 districts (amphoe and Bangkok khet) and 7,436
subdistricts (tambon and Bangkok khwaeng), keyed by their DOPA codes, with Thai
and English names, ISO 3166-2 province codes and postal codes. Every row comes
from an official source pinned in [`data/sources.json`](data/sources.json), and
`tools/generate.php` rebuilds it.

- An API that reads like Laravel: Laravel collections, lazy relations
  (`$subdistrict->district->province`), `*OrFail` lookups.
- Validation rules, including "this district is in that province".
- Eloquent casts that store a code and give you the model.
- Works in plain PHP too; Laravel is optional.

## Requirements

- PHP 8.2 or newer, with `ext-mbstring`
- `illuminate/collections` 12 or 13, installed with the package
- Laravel 12 or 13 for the Laravel integration (optional)

## Install

```sh
composer require farzai/thailand-address
```

In Laravel, package discovery registers the service provider and the
`ThailandAddress` facade alias. Nothing needs publishing.

## Laravel

### Validation

Validate a Thai address form with rule objects:

```php
use Illuminate\Validation\Rule;

$request->validate([
    'province'    => ['required', Rule::thaiProvince()],
    'district'    => ['required', Rule::thaiDistrict()->inProvince('province')],
    'subdistrict' => ['required', Rule::thaiSubdistrict()->inDistrict('district')],
    'postal_code' => ['required', Rule::thaiPostalCode()->forSubdistrict('subdistrict')],
]);
```

String rules do the same checks:

```php
$request->validate([
    'province'    => 'required|thai_province',
    'district'    => 'required|thai_district:province',
    'subdistrict' => 'required|thai_subdistrict:district',
    'postal_code' => 'required|thai_postal_code:subdistrict',
]);
```

| Rule | Passes when |
|---|---|
| `thai_province` | the value is a province code, e.g. `10` |
| `thai_district`, `thai_district:field` | the value is a district code, e.g. `1001`, in the province given in `field` |
| `thai_subdistrict`, `thai_subdistrict:field` | the value is a subdistrict code, e.g. `100101`, in the district given in `field` |
| `thai_postal_code`, `thai_postal_code:field` | the value is a Thai postal code, and it serves the subdistrict given in `field` |

- **One error per mistake.** A parent check passes when the parent field is
  empty or invalid; that field's own rule reports it, so the user sees one error.
- **Arrays work.** Wildcards pair by position:
  `'addresses.*.district' => 'thai_district:addresses.*.province'`.
- **No postal code data.** A subdistrict with no known postal code accepts any
  valid Thai postal code.
- **Rule classes.** They are also usable directly, without the macros:
  `ThaiProvince::make()`, `ThaiDistrict::make()->inProvince('province')`, and
  so on, under `Farzai\ThailandAddress\Laravel\Rules`.

Messages come in English and Thai, under `thailand-address::validation`.
Publish them to change the wording:

```sh
php artisan vendor:publish --tag=thailand-address-lang
```

A message you define for a string rule in your own `lang/*/validation.php`, or
pass to the validator, takes precedence. It can use `:other` for the parent
field's name.

### Eloquent casts

```php
use Farzai\ThailandAddress\Laravel\Casts\AsDistrict;
use Farzai\ThailandAddress\Laravel\Casts\AsProvince;
use Farzai\ThailandAddress\Laravel\Casts\AsSubdistrict;

class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'province_code'    => AsProvince::class,
            'district_code'    => AsDistrict::class,
            'subdistrict_code' => AsSubdistrict::class,
        ];
    }
}

$customer->subdistrict_code = '100101';             // or a Subdistrict, or 100101
$customer->subdistrict_code->district->province->nameTh; // 'กรุงเทพมหานคร'
```

- **Storage.** The column stores the code as a string. Reading it back gives
  the model.
- **Unknown codes.** Setting an unknown code throws `NotFoundException`, as
  Laravel's enum casts do. Reading one back gives `null`: a stored code can
  stop existing when DOPA abolishes an area, and a row read must not fail.
- **Blank input.** Setting `''` (a blank form field) or `null` clears the
  column.
- **Wrong level.** Setting a model of the wrong level throws
  `InvalidArgumentException`.

### Facade and injection

```php
use Farzai\ThailandAddress\AddressRepository;
use Farzai\ThailandAddress\Laravel\Facades\ThailandAddress;

ThailandAddress::province('10');

public function __construct(private AddressRepository $addresses) {}
```

The container holds one `AddressRepository`. The `ThailandAddress` facade and
the framework-agnostic `Thailand` class below both use it.

## Plain PHP (and everything else)

```php
use Farzai\ThailandAddress\Thailand;

Thailand::provinces();                          // Collection<Province>, 77 items
Thailand::province('10');                       // ?Province, by DOPA code
Thailand::provinceOrFail('10');                 // Province, or NotFoundException
Thailand::provinces()->firstWhere('nameEn', 'Bangkok');

Thailand::province('10')->districts();          // Collection<District>
Thailand::province('10')->subdistricts();       // every subdistrict in Bangkok
Thailand::district('1001')->subdistricts();     // Collection<Subdistrict>

$s = Thailand::subdistrict('100101');
$s->nameTh;                                     // 'พระบรมมหาราชวัง'
$s->district->province->nameTh;                 // 'กรุงเทพมหานคร' (lazy relations)
$s->postalCodes;                                // ['10200']

Thailand::postalCode('10200');                  // Collection<Subdistrict>
Thailand::search('บางรัก');                      // Thai or English names, every level

$address = $s->toAddress();                     // Address
$address->administrativeArea;                   // 'กรุงเทพมหานคร'
$address->locality;                             // 'เขตพระนคร'
$address->dependentLocality;                    // 'พระบรมมหาราชวัง'
$address->postalCode;                           // '10200'
$address->countryCode;                          // 'TH'
$address->inLocale('en')->toArray();            // the same, with English names
```

- **Collections.** They are Laravel collections (`Illuminate\Support\Collection`),
  so `where`, `firstWhere`, `pluck`, `map`, `sortBy` and the rest work as in
  Laravel.
- **Indexing.** Collections are list-indexed, so JSON gives an array.
- **Models.** `Province`, `District` and `Subdistrict` are read-only, and
  implement `Arrayable` and `JsonSerializable`.
- **Lookups.** Every lookup takes a code as a string or an int. A miss gives
  `null`, or an empty collection.
- **Loading.** Each level's data loads on first use, once per process: a
  province lookup reads only the 77 provinces, not the 7,436 subdistricts.
- **Serializing.** A model serializes as its code (a few dozen bytes) and is
  rebuilt through `Thailand::repository()` when read back, so a queued job or
  a cache entry stays small. If the code no longer exists when the payload is
  read back (DOPA abolished the area and the data was updated), unserializing
  throws `NotFoundException`, naming the model class and the stale code. Store
  the code instead of the model where a payload can outlive a data update.
  `var_dump()` shows the model's fields.

### Testing with a fake

`Thailand` is a static facade over an `AddressRepository`. Swap in a small fake:

```php
use Farzai\ThailandAddress\AddressRepository;
use Farzai\ThailandAddress\Thailand;

Thailand::swap(AddressRepository::fromArrays(
    ['99' => ['name_th' => 'ทดสอบ', 'name_en' => 'Test', 'iso_code' => 'TH-99']],
    ['9901' => ['name_th' => 'เมืองทดสอบ', 'name_en' => 'Mueang Test']],
    ['990101' => ['name_th' => 'ในเมือง', 'name_en' => 'Nai Mueang', 'postal_codes' => ['99000']]],
));

Thailand::swap(null); // back to the real data
```

In Laravel, any one of `Thailand::swap($fake)`, `ThailandAddress::swap($fake)`
or binding the fake to `AddressRepository::class` is enough: `Thailand`, the
facade, the validation rules and the casts all read the container's
repository, and `Thailand::swap()` rebinds it there.

### Codes

The codes are DOPA's administrative codes, as strings:

| Level | Example | Digits |
|---|---|---|
| Province (จังหวัด) | `10` | 2 |
| District: amphoe (อำเภอ) or khet (เขต) | `1001` | 4: the province's 2 digits, then 2 more |
| Subdistrict: tambon (ตำบล) or khwaeng (แขวง) | `100101` | 6: the district's 4 digits, then 2 more |

DOPA publishes 8-digit codes (`PPAATTMM`). The package uses the first 6, which
are the province, district and subdistrict.

## Names and standards

| This package | DOPA (Thai) | ISO 3166-2:TH | libaddressinput / CLDR | schema.org `PostalAddress` |
|---|---|---|---|---|
| `Province` | จังหวัด | province (`TH-10` … `TH-96`) | `administrativeArea` | `addressRegion` |
| `District` | อำเภอ, เขต in Bangkok | | `locality` | `addressLocality` |
| `Subdistrict` | ตำบล, แขวง in Bangkok | | `dependentLocality` | part of `streetAddress` (no own field) |
| `Subdistrict::$postalCodes` | รหัสไปรษณีย์ | | `postalCode` | `postalCode` |
| `Address::$countryCode` (`TH`) | | | `countryCode` | `addressCountry` |

- Bangkok's khet are `District`s and its khwaeng are `Subdistrict`s, as in DOPA's
  own English terms. Bangkok itself is a `Province` (ISO 3166-2 calls it a
  metropolitan administration, `TH-10`).
- PHP properties are camelCase (`nameTh`, `provinceCode`, `postalCodes`,
  `isoCode`). `toArray()` on an area, the generated data files and
  `data/sources.json` use the same names in snake_case (`name_th`,
  `province_code`, `postal_codes`, `iso_code`). `Address::toArray()` uses the
  libaddressinput names above.
- ISO 3166-2 also lists `TH-S` (Pattaya, a special administrative city). DOPA
  counts Pattaya as part of Chon Buri (`TH-20`), so it has no `Province` here.

## Postal codes

- A subdistrict can have more than one postal code, and one postal code can
  serve many subdistricts, so `postalCodes` is a list of strings.
- 7,386 of the 7,436 subdistricts have at least one postal code. The other 50 have
  an empty list because neither source covers them.
- For 82 subdistricts the two sources disagree, and the list holds the postal
  codes from both, except that a code only one source gives is dropped when
  its first two digits are not a postal area both sources agree on in that
  province. Three real cross-province codes are kept on a checked allowlist.
  Every postal code belongs to its subdistrict's province, and a test checks
  that.
- `toAddress()` fills `postalCode` only when the subdistrict has exactly one, or
  when you pass one it has; it never guesses among several.
- Thailand Post is the authority on postal codes. Check with it before relying
  on a postal code for something that matters.

The per-source numbers are in `data/sources.json` under `postal_codes`.

## Data sources and licences

| Data | Source | Licence |
|---|---|---|
| Codes, Thai names, English names | DOPA, ทำเนียบท้องที่ [`ccaatt.xlsx`](https://stat.bora.dopa.go.th/dload/ccaatt.xlsx) ([landing page](https://stat.bora.dopa.go.th/dload/fccaa.htm)), data as of 1 September 2023 | None is stated on the page. It is published government record data. |
| Postal codes, by subdistrict code | Thailand Post, the [track web app](https://track.thailandpost.co.th/)'s subdistrict list (one request) | None is stated. See Thailand Post's [terms](https://postbase.thailandpost.co.th/th/terms-and-conditions). |
| Postal codes, by name | MDES, [สถานที่ทำการไปรษณีย์](https://data.go.th/dataset/postoffice1) (post offices) on data.go.th, `postal-2026.06.30.csv` | Creative Commons Attribution. Attribution: Office of the Permanent Secretary, Ministry of Digital Economy and Society. |
| ISO 3166-2:TH province codes | Debian [iso-codes](https://salsa.debian.org/iso-codes-team/iso-codes) v4.20.1, `iso_3166-2.json`. Each code is joined to its DOPA province by number and must agree by name (cross-checked against Wikipedia's ISO 3166-2:TH table, 2026-10-14) | The codes are ISO 3166-2; iso-codes is LGPL-2.1-or-later. Only the 77 codes are used. |

Things to know about the data:

- **Cancelled areas are dropped.** DOPA marks a cancelled area with a non-zero
  cancel date and a `*` after its name. Those rows are not shipped.
- **English names are DOPA's romanisation**, which is close to RTGS but not
  strict (for example "Vadhana" for วัฒนา).
- **Names are verbatim.** Bangkok khet names keep their Thai prefix (`เขตพระนคร`);
  English names have no prefix (`Phra Nakhon`).
- **The DOPA file is dated 2023-09-01.** Changes made after that date are not
  in it.

No data from kongvut/thai-province-data or any project derived from it is used.

## Refreshing the data

The data comes from published snapshots of the
[Thai address dataset](https://data.farzai.com/thailand-address/index.html), which
is built from the official sources and published as immutable, versioned
snapshots. A weekly workflow (`.github/workflows/update-data.yml`) checks for a
newer one and opens a pull request; nothing is merged automatically. By hand:

```sh
php tools/import-snapshot.php                        # the latest published snapshot
php tools/import-snapshot.php --snapshot=2026-11-01  # a pinned one
```

Before writing anything, the importer checks:
- the snapshot is listed as published in `v1/snapshots.json`;
- its manifest matches the sha256 recorded there;
- every file matches the manifest.

Then it rewrites `data/*.php`, and `data/sources.json`, which names the snapshot. `data/` keeps its shape: active areas only, with Bangkok's khet named as before (`เขตพระนคร`).

### The old generator

The generator below still fetches the sources directly. It will be reduced to
the importer above.

```sh
php tools/generate.php              # use tools/cache/, fetch what is missing
php tools/generate.php --refresh    # fetch every source again
php tools/generate.php --offline    # rebuild from the cache only
php tools/generate.php --verify     # check the cached sources' sha256 against data/sources.json; data/ is not written
php tools/generate.php --verify --refresh  # the same against what the sources serve today; re-fetches into tools/cache/
```

It needs `ext-zip`, `ext-simplexml` and `ext-mbstring`. A run:

1. Fetches the four sources into `tools/cache/`, one request each, at least
   2 seconds apart. Each request sends an honest User-Agent. On HTTP 401 or 403,
   or on anything that looks like a block page, the run stops. A redirect is
   followed only to another https URL; one to plain http stops the run.
2. Keeps DOPA's active rows, adds each province's ISO 3166-2 code, and joins
   the postal codes:
   - Thailand Post by subdistrict code. A row is withheld when its name
     disagrees with DOPA's.
   - MDES by province, district and subdistrict name.
   - A code only one source gives is dropped if it falls outside the
     province's postal areas (see `CROSS_PROVINCE_POSTAL_CODES` for the
     exceptions). Disagreements and drops are written to
     `tools/review/postal-codes.csv` for review.
3. Fails if a sanity check breaks: 77 provinces, 50 Bangkok khet, no orphans,
   no empty names.
4. Rewrites `data/*.php` and `data/sources.json`. The manifest records each
   source's URL, fetch time, `Last-Modified`, ETag, size, sha256 and licence.

Then run `composer test`. The tests pin the exact counts, so a real change in
the sources shows up as a failing test. Update that test on purpose, and
describe the change in the CHANGELOG.

Suggested cadence: DOPA quarterly, and the postal code sources twice a year.

## Upgrading from 1.x

The package has a new name. `parsilver/thailand-provinces-php` 1.x is
abandoned in favour of `farzai/thailand-address`, which starts at 2.0:

```sh
composer remove parsilver/thailand-provinces-php
composer require farzai/thailand-address
```

2.0 is a rewrite. Nothing from the 1.x API remains.

| 1.x | 2.0 |
|---|---|
| `PA\ProvinceTh\Factory::province()` | `Thailand::provinces()` |
| `Factory::amphure()` | `Thailand::districts()` |
| `Factory::district()` (tambon) | `Thailand::subdistricts()` |
| `Factory::geography()` | removed (regions are not in the official sources) |
| `->find(1)` with a row id | `Thailand::province('10')` with the DOPA code |
| `find()` returning the whole table on a miss | `null` |
| `->amphures()`, `->districts()` | `$province->districts()`, `$district->subdistricts()` |
| `zip_code` (one string) | `postalCodes` (a list) |
| `Factory::amphure()` / `amphures()` names | `District`, `districts()`: no Thai transliterations in the API |
| `name_th`, `name_en` array keys | `nameTh`, `nameEn` properties; `toArray()` keeps `name_th`, `name_en` |

What else changed:

- **Ids changed.** 1.x keyed provinces and districts by its own row ids
  (province 1 was Bangkok). 2.0 uses DOPA codes (Bangkok is `10`). If you
  stored 1.x province or district ids, map them through the 1.x `code` field,
  which holds the DOPA code. 1.x subdistrict ids were already 6-digit codes,
  except in Bueng Kan (38), where they did not match DOPA's (#7).
- **The data was regenerated from the official sources.** Abolished areas are
  gone, Bueng Kan's districts appear once under code 38, and the missing Bangkok
  khwaeng and Chiang Mai's Galyani Vadhana district are present.
- **Requirements:** PHP 8.2 or newer. Laravel 12 or 13 for the Laravel
  integration. Laravel 10 and 11 are not supported: they are end-of-life, and
  Composer refuses to install them because of open security advisories.

`parsilver/thailand-provinces-php` is frozen at 1.0.3 and gets no further
releases. The source of both lives in this repository.

## Not in 2.0

These may come later. They are not in this release:

- Database migrations and seeders for the address tables
- Livewire or Blade components for cascading province / district / subdistrict
  dropdowns

## Development

```sh
composer install
composer test      # vendor/bin/pest
```

### Testing

- **Tests are written in [Pest](https://pestphp.com).** `tests/Core` covers
  the plain-PHP API, the data and the generator's helpers. `tests/Laravel`
  boots a Testbench application through `uses(...)->in('Laravel')` in
  `tests/Pest.php`.
- **Pest versions.** `require-dev` allows Pest 3, 4 and 5, so Composer
  installs the newest one your PHP can run: Pest 5 needs PHP 8.4, Pest 4 needs
  PHP 8.3, and Pest 3 runs on 8.2. Pest 5 also needs PHPUnit 13, which only
  Testbench 11 (Laravel 13) allows, so a Laravel 12 setup gets Pest 4 at most. The tests use only what the three share
  (`test()`, `uses()`, `beforeEach()`, `afterEach()`, `dataset()`,
  `->with()`), so they run unchanged on each.
- **CI.** CI runs PHP 8.2 to 8.5 against Laravel 12 and 13, prints the Pest
  version each job resolved, and checks that the dist excludes `tests/` and
  `tools/`.

## License

The code is MIT. See [LICENSE](LICENSE). The data keeps the terms of its
sources, listed above.
