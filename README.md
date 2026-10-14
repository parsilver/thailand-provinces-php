# Thailand provinces, districts, subdistricts and postal codes (PHP)

[![tests](https://github.com/parsilver/thailand-provinces-php/actions/workflows/tests.yml/badge.svg)](https://github.com/parsilver/thailand-provinces-php/actions/workflows/tests.yml)

ข้อมูลจังหวัด อำเภอ/เขต ตำบล/แขวง และรหัสไปรษณีย์ของประเทศไทย จากแหล่งข้อมูลทางการ
(กรมการปกครอง, ไปรษณีย์ไทย, กระทรวงดิจิทัลฯ) พร้อมสคริปต์สร้างข้อมูลใหม่ได้เอง

Thailand's 77 provinces, 928 districts (amphoe and Bangkok khet) and 7,436
subdistricts (tambon and Bangkok khwaeng), keyed by their DOPA codes, with Thai
and English names, ISO 3166-2 province codes and postal codes. Every row comes from an official source pinned
in [`data/sources.json`](data/sources.json), and `tools/generate.php` rebuilds it.

## Requirements

- PHP 8.2 or newer, with `ext-mbstring`
- No framework

## Install

```sh
composer require farzai/thailand-address
```

## Usage

```php
use Farzai\ThailandAddress\Thailand;

$th = new Thailand();

// Find by DOPA code. A code that does not exist returns null.
$bangkok = $th->province('10');          // Province, or null
$bangkok->nameTh;                        // 'กรุงเทพมหานคร'
$bangkok->nameEn;                        // 'Bangkok'
$bangkok->isoCode;                       // 'TH-10' (ISO 3166-2:TH)

$khet = $th->district('1001');           // District 'เขตพระนคร'
$khet->provinceCode;                     // '10'

$khwaeng = $th->subdistrict('100101');   // Subdistrict 'พระบรมมหาราชวัง'
$khwaeng->districtCode;                  // '1001'
$khwaeng->provinceCode;                  // '10'
$khwaeng->postalCodes;                   // ['10200']

$th->province('99');                     // null

// Children of a parent, by code or by object.
$th->districtsOf('38');                  // the 8 districts of Bueng Kan
$th->subdistrictsOf($khet);              // the 12 khwaeng of Phra Nakhon

// Search by Thai or English name: a case-insensitive "contains" match.
$th->provinces()->search('เชียง');        // Chiang Mai, Chiang Rai
$th->districts()->search('bueng kan');

// Every subdistrict a postal code serves.
$th->subdistrictsByPostalCode('10200');

// A full address, with the libaddressinput / CLDR field names.
$address = $th->address('100101');      // Address, or null
$address->toArray();                     // Thai names
// ['countryCode' => 'TH', 'administrativeArea' => 'กรุงเทพมหานคร',
//  'locality' => 'เขตพระนคร', 'dependentLocality' => 'พระบรมมหาราชวัง',
//  'postalCode' => '10200']
$address->toArray('en');                 // English names
$th->address('100101', '10200');         // choose among several postal codes

// Collections are countable, iterable and JSON-serialisable.
count($th->subdistricts());              // 7436
foreach ($th->districtsOf('10') as $district) {
    echo $district->code, ' ', $district->nameTh, PHP_EOL;
}
$th->districtsOf('38')->find('3801');    // District, or null
$th->provinces()->first();
$th->provinces()->all();                 // list<Province>
$th->provinces()->codes();               // ['10', '11', ...]
$th->provinces()->filter(fn ($p) => str_starts_with($p->code, '1'));
json_encode($th->districtsOf('38'), JSON_UNESCAPED_UNICODE);
```

### Codes

The codes are DOPA's administrative codes, as strings. Integers are accepted
on input.

| Level | Example | Digits |
|---|---|---|
| Province (จังหวัด) | `10` | 2 |
| District: amphoe (อำเภอ) or khet (เขต) | `1001` | 4: the province's 2 digits, then 2 more |
| Subdistrict: tambon (ตำบล) or khwaeng (แขวง) | `100101` | 6: the district's 4 digits, then 2 more |

DOPA publishes 8-digit codes (`PPAATTMM`). The package uses the first 6, which
are the province, district and subdistrict.

### Names and standards

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

### Postal codes

- A subdistrict can have more than one postal code, and one postal code can
  serve many subdistricts, so `postalCodes` is a list of strings.
- 7,386 of the 7,436 subdistricts have at least one postal code. The other 50 have
  an empty list because neither source covers them.
- For 92 subdistricts the two sources disagree, and the list holds the postal
  codes from both.
- `address()` fills `postalCode` only when the subdistrict has exactly one, or
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

The generator is in the repository but not in the Composer package.

```sh
php tools/generate.php              # use tools/cache/, fetch what is missing
php tools/generate.php --refresh    # fetch every source again
php tools/generate.php --offline    # rebuild from the cache only
```

It needs `ext-zip`, `ext-simplexml` and `ext-mbstring`. A run:

1. Fetches the four sources into `tools/cache/`, one request each, at least
   2 seconds apart. Each request sends an honest User-Agent. On HTTP 401 or 403,
   or on anything that looks like a block page, the run stops.
2. Keeps DOPA's active rows, adds each province's ISO 3166-2 code, and joins
   the postal codes:
   - Thailand Post by subdistrict code. A row is withheld when its name
     disagrees with DOPA's.
   - MDES by province, district and subdistrict name.
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
| `PA\ProvinceTh\Factory::province()` | `(new Farzai\ThailandAddress\Thailand())->provinces()` |
| `Factory::amphure()` | `$th->districts()` |
| `Factory::district()` (tambon) | `$th->subdistricts()` |
| `Factory::geography()` | removed (regions are not in the official sources) |
| `->find(1)` with a row id | `$th->province('10')` with the DOPA code |
| `find()` returning the whole table on a miss | `null` |
| `->amphures()`, `->districts()` | `$th->districtsOf($code)`, `$th->subdistrictsOf($code)` |
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
- **Requirements:** PHP 8.2 or newer.

`parsilver/thailand-provinces-php` is frozen at 1.0.3 and gets no further
releases. The source of both lives in this repository.

## Development

```sh
composer install
composer test
```

## License

The code is MIT. See [LICENSE](LICENSE). The data keeps the terms of its
sources, listed above.
