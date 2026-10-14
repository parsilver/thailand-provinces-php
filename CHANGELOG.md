# Changelog

## 2.0.0 (unreleased)

A rewrite. See "Upgrading from 1.x" in the README.

### Changed

- New API under `Farzai\ThailandAddress`. `Thailand` is the entry point, with
  read-only `Province`, `District` and `Subdistrict` value objects and a
  countable, iterable `Collection`.
- Areas are keyed by DOPA code (`10`, `1001`, `100101`), not by row id.
- A subdistrict has a list of `postalCodes` instead of one `zip_code`.
- Standard names throughout: Province, District, Subdistrict (Bangkok khet
  and khwaeng are District and Subdistrict), `postalCodes`, and `isoCode`.
- PHP 8.2 or newer is required.

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

- `Province::$isoCode` (ISO 3166-2:TH, e.g. `TH-10`).
- `Thailand::address()` and `Address`, whose `toArray()` uses the
  libaddressinput / CLDR names (`administrativeArea`, `locality`,
  `dependentLocality`, `postalCode`, `countryCode`).
- `tools/generate.php` rebuilds the data from the sources (#8).
- CI on PHP 8.2 to 8.5, plus a check that the dist excludes dev files (#14).
- A LICENSE file, and a `.gitattributes` that keeps tests and tools out of
  the dist (#11, #16).

### Removed

- The 1.x API: `PA\ProvinceTh\Factory`, `Provider\*`, `Support\Collection`.
- Geography (regions), which no official source in use provides.

## 1.0.3

Last 1.x release. 1.x is frozen.
