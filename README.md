# Thailand provinces, districts, subdistricts and postcodes (PHP)

[![tests](https://github.com/parsilver/thailand-provinces-php/actions/workflows/tests.yml/badge.svg)](https://github.com/parsilver/thailand-provinces-php/actions/workflows/tests.yml)

ข้อมูลจังหวัด อำเภอ/เขต ตำบล/แขวง และรหัสไปรษณีย์ของประเทศไทย จากแหล่งข้อมูลทางการ
(กรมการปกครอง, ไปรษณีย์ไทย, กระทรวงดิจิทัลฯ) พร้อมสคริปต์สร้างข้อมูลใหม่ได้เอง

Thailand's 77 provinces, 928 districts (amphoe and Bangkok khet) and 7,436
subdistricts (tambon and Bangkok khwaeng), keyed by their DOPA codes, with Thai
and English names and postcodes. Every row comes from an official source pinned
in [`data/sources.json`](data/sources.json), and `tools/generate.php` rebuilds it.

## Requirements

- PHP 8.2 or newer, with `ext-mbstring`
- No framework

## Install

```sh
composer require parsilver/thailand-provinces-php:^2.0
```

## Usage

```php
use Farzai\ThailandAddress\Thailand;

$th = new Thailand();

// Find by DOPA code. A code that does not exist returns null.
$bangkok = $th->province('10');          // Province, or null
$bangkok->nameTh;                        // 'กรุงเทพมหานคร'
$bangkok->nameEn;                        // 'Bangkok'

$khet = $th->district('1001');           // District 'เขตพระนคร'
$khet->provinceCode;                     // '10'

$khwaeng = $th->subdistrict('100101');   // Subdistrict 'พระบรมมหาราชวัง'
$khwaeng->districtCode;                  // '1001'
$khwaeng->provinceCode;                  // '10'
$khwaeng->postcodes;                     // ['10200']

$th->province('99');                     // null

// Children of a parent, by code or by object.
$th->districtsOf('38');                  // the 8 districts of Bueng Kan
$th->subdistrictsOf($khet);              // the 12 khwaeng of Phra Nakhon

// Search by Thai or English name: a case-insensitive "contains" match.
$th->provinces()->search('เชียง');        // Chiang Mai, Chiang Rai
$th->districts()->search('bueng kan');

// Every subdistrict a postcode serves.
$th->subdistrictsByPostcode('10200');

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

### Postcodes

- A subdistrict can have more than one postcode, and one postcode can serve
  many subdistricts, so `postcodes` is a list.
- 7,386 of the 7,436 subdistricts have at least one postcode. The other 50 have
  an empty list because neither source covers them.
- For 92 subdistricts the two sources disagree, and the list holds the postcodes
  from both.
- Thailand Post is the authority on postcodes. Check with it before relying on
  a postcode for something that matters.

The per-source numbers are in `data/sources.json` under `postcodes`.

## Data sources and licences

| Data | Source | Licence |
|---|---|---|
| Codes, Thai names, English names | DOPA, ทำเนียบท้องที่ [`ccaatt.xlsx`](https://stat.bora.dopa.go.th/dload/ccaatt.xlsx) ([landing page](https://stat.bora.dopa.go.th/dload/fccaa.htm)), data as of 1 September 2023 | None is stated on the page. It is published government record data. |
| Postcodes, by subdistrict code | Thailand Post, the [track web app](https://track.thailandpost.co.th/)'s subdistrict list (one request) | None is stated. See Thailand Post's [terms](https://postbase.thailandpost.co.th/th/terms-and-conditions). |
| Postcodes, by name | MDES, [สถานที่ทำการไปรษณีย์](https://data.go.th/dataset/postoffice1) (post offices) on data.go.th, `postal-2026.06.30.csv` | Creative Commons Attribution. Attribution: Office of the Permanent Secretary, Ministry of Digital Economy and Society. |

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

1. Fetches the three sources into `tools/cache/`, one request each, at least
   2 seconds apart. Each request sends an honest User-Agent. On HTTP 401 or 403,
   or on anything that looks like a block page, the run stops.
2. Keeps DOPA's active rows and joins the postcodes:
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

Suggested cadence: DOPA quarterly, and the postcode sources twice a year.

## Upgrading from 1.x

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
| `zip_code` (one string) | `postcodes` (a list) |
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

1.x is frozen at 1.0.3.

## Development

```sh
composer install
composer test
```

## License

The code is MIT. See [LICENSE](LICENSE). The data keeps the terms of its
sources, listed above.
