# Changelog

## 2.0.2 (unreleased)

### Fixed

- Postal codes from another province's postal area are no longer shipped
  (#19). The generator joins two sources, and a code only one of them gave
  is now dropped when its first two digits are not a postal area both sources
  agree on in that province. This removed 10 invalid codes from 10
  subdistricts, among them `73697` for Sung Noen, Buri Ram, which validation
  accepted and `Thailand::postalCode('73697')` returned. No subdistrict lost
  its only code. Each affected subdistrict now has one code again, so
  `toAddress()` fills `postalCode` for it.
- Three real cross-province codes, each a post office in the neighbouring
  province, are kept on an allowlist with the evidence from both sources.

- The object validation rules (`Rule::thaiDistrict()->inProvince('addresses.*.province')`
  and the subdistrict and postal-code ones) checked a wildcard parent against
  the wrong row when an array key contained a dot, so `addresses[a.b][district]`
  passed with a district outside `addresses[a.b][province]` (#20). The parent
  is now found in the request data itself, and a wildcard parent that is
  missing from the row fails instead of passing. The string rules gave such a
  row the wrong message; that is fixed too.

### Added

- `tools/generate.php --verify` checks every source against the sha256 in
  `data/sources.json` without writing `data/`. The generator no longer follows a
  redirect from https to plain http.
- A data test that every postal code belongs to its subdistrict's province,
  and pinned known codes.
- `tools/review/postal-codes.csv`: every subdistrict where the two sources
  disagree, and every code dropped, for a person to read on a refresh.

## 2.0.1 (2026-10-14)

### Fixed

- Unserializing a model whose code no longer exists still throws
  `NotFoundException`, as in 2.0.0, but the message now names the model class
  and the stale code, with the lookup's exception as the previous one. The
  README now says so instead of calling models safe in queues and caches
  without qualification.

### Changed

- `pestphp/pest-plugin-laravel` is no longer a dev dependency; no test used it.

## 2.0.0 (2026-10-14)

A rewrite. See "Upgrading from 1.x" in the README.

### Changed

- The Composer package is now `farzai/thailand-address`.
  `parsilver/thailand-provinces-php` (1.x) is abandoned in its favour. The
  repository stays at github.com/parsilver/thailand-provinces-php.
- New API under `Farzai\ThailandAddress`, in the style of Laravel:
  - `Thailand` is a static facade over an injectable `AddressRepository`.
  - It returns Laravel collections of read-only `Province`, `District` and
    `Subdistrict` models.
  - Models have lazy relations (`$subdistrict->district->province`) and
    `*OrFail` lookups that throw `NotFoundException`.
  - It works in plain PHP, with `illuminate/collections` as its one dependency.
- Areas are keyed by DOPA code (`10`, `1001`, `100101`), not by row id.
- A subdistrict has a list of `postalCodes` instead of one `zip_code`.
- Standard names throughout: Province, District, Subdistrict (Bangkok khet
  and khwaeng are District and Subdistrict), `postalCodes`, and `isoCode`.
- PHP 8.2 or newer is required; `illuminate/collections` 12 or 13.

### Data

- Regenerated from official sources only, pinned in `data/sources.json`:
  - DOPA ccaatt.xlsx, as of 2023-09-01: 77 provinces, 928 districts, 7,436
    subdistricts.
  - Postal codes from Thailand Post and the MDES post-office dataset. 7,386
    subdistricts have at least one.
  - ISO 3166-2:TH codes from Debian iso-codes v4.20.1, checked by name.
- Cancelled areas (names marked `*`) are no longer shipped (#6).
- Bueng Kan appears once, under code 38, with DOPA's subdistrict codes (#7).
- Missing areas are present, among them Bangkok's newer khwaeng and Chiang
  Mai's Galyani Vadhana district (#8).
- Typos, doubled spaces and tabs in names are gone (#16).

### Fixed

- `find()` and every lookup return `null` on a miss instead of the whole table
  (#9).
- No PHP 8.4+ deprecations; `count()` and `foreach` work on collections (#13).

### Added

- Laravel 12 and 13 integration, auto-discovered:
  - a service provider with a singleton repository, and the `ThailandAddress`
    facade;
  - validation rules as objects (`Rule::thaiDistrict()->inProvince('province')`)
    and as strings (`thai_district:province`), with English and Thai messages;
  - Eloquent casts `AsProvince`, `AsDistrict` and `AsSubdistrict`. Setting an
    unknown code throws; reading a stored code that no longer exists gives
    `null`; setting `''` (a blank form field) or `null` clears the column.
- `Thailand::search()` across every level, and `Thailand::postalCode()`.
- Each level's data loads on first use, once per process: a province lookup
  reads only the provinces file, never the 7,436 subdistricts.
- Models serialize as their code and are rebuilt through
  `Thailand::repository()` when read back, so a queued job or cache entry
  holds a few dozen bytes instead of the data set.
- `Thailand::swap()` replaces the repository for tests. Inside Laravel it
  rebinds the container's `AddressRepository`, so the facade, the casts and
  the rules all see the swapped one.
- `Province::$isoCode` (ISO 3166-2:TH, e.g. `TH-10`).
- `Subdistrict::toAddress()` and `Address`, which use the libaddressinput /
  CLDR names (`administrativeArea`, `locality`, `dependentLocality`,
  `postalCode`, `countryCode`).
- `tools/generate.php` rebuilds the data from the sources (#8).
- CI on PHP 8.2 to 8.5, plus a check that the dist excludes dev files (#14).
- A LICENSE file, and a `.gitattributes` that keeps tests and tools out of
  the dist (#11, #16).

### Removed

- The 1.x API: `PA\ProvinceTh\Factory`, `Provider\*`, `Support\Collection`.
- Geography (regions), which no official source in use provides.

## 1.0.3

Last release of `parsilver/thailand-provinces-php`. 1.x is frozen.
