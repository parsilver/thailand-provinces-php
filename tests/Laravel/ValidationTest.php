<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests\Laravel;

use Farzai\ThailandAddress\Laravel\Rules\ThaiDistrict;
use Farzai\ThailandAddress\Laravel\Rules\ThaiPostalCode;
use Farzai\ThailandAddress\Laravel\Rules\ThaiProvince;
use Farzai\ThailandAddress\Laravel\Rules\ThaiSubdistrict;
use Farzai\ThailandAddress\Thailand;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** A Bangkok address that is right at every level. */
const VALID = ['province' => '10', 'district' => '1001', 'subdistrict' => '100101', 'postal_code' => '10200'];

/** @return array<string, list<mixed>> */
function objectRules(): array
{
    return [
        'province' => ['required', Rule::thaiProvince()],
        'district' => ['required', Rule::thaiDistrict()->inProvince('province')],
        'subdistrict' => ['required', Rule::thaiSubdistrict()->inDistrict('district')],
        'postal_code' => ['required', Rule::thaiPostalCode()->forSubdistrict('subdistrict')],
    ];
}

/** @return array<string, string> */
function stringRules(): array
{
    return [
        'province' => 'required|thai_province',
        'district' => 'required|thai_district:province',
        'subdistrict' => 'required|thai_subdistrict:district',
        'postal_code' => 'required|thai_postal_code:subdistrict',
    ];
}

/**
 * @param array<string, mixed> $data
 * @return array<string, list<string>>
 */
function validationErrors(string $style, array $data): array
{
    return Validator::make($data, $style === 'object' ? objectRules() : stringRules())->errors()->toArray();
}

dataset('rule styles', [
    'rule objects' => ['object'],
    'string rules' => ['string'],
]);

test('a consistent address passes', function (string $style) {
    $this->assertSame([], validationErrors($style, VALID));
    $this->assertSame([], validationErrors($style, ['province' => 38, 'district' => 3801, 'subdistrict' => 380101, 'postal_code' => '38000']));
})->with('rule styles');

test('unknown codes fail each with its own message', function (string $style) {
    $errors = validationErrors($style, ['province' => '99', 'district' => '9999', 'subdistrict' => 'x', 'postal_code' => '1020']);
    $this->assertSame([
        'province' => ['The province must be a valid Thai province code.'],
        'district' => ['The district must be a valid Thai district code.'],
        'subdistrict' => ['The subdistrict must be a valid Thai subdistrict code.'],
        'postal_code' => ['The postal code must be a valid Thai postal code.'],
    ], $errors);
})->with('rule styles');

test('each level must belong to its parent', function (string $style) {
    $errors = validationErrors($style, ['province' => '38', 'district' => '1001', 'subdistrict' => '380101', 'postal_code' => '50000']);
    $this->assertSame([
        'district' => ['The district is not in the selected province.'],
        'subdistrict' => ['The subdistrict is not in the selected district.'],
        'postal_code' => ['The postal code does not serve the selected subdistrict.'],
    ], $errors);
})->with('rule styles');

test('an invalid parent is reported once on the parent', function (string $style) {
    $errors = validationErrors($style, ['province' => '99', 'district' => '1001', 'subdistrict' => '100101', 'postal_code' => '10200']);
    $this->assertSame(['province'], array_keys($errors));
})->with('rule styles');

test('messages are translated to thai', function (string $style) {
    $this->app->setLocale('th');
    $errors = validationErrors($style, ['province' => '38', 'district' => '1001', 'subdistrict' => '999999', 'postal_code' => '10200']);
    $this->assertSame([
        'district' => ['district ไม่อยู่ในprovinceที่เลือก'],
        'subdistrict' => ['subdistrict ไม่ใช่รหัสตำบลหรือแขวงที่ถูกต้อง'],
    ], $errors);
})->with('rule styles');

test('custom attribute names reach both placeholders', function () {
    $validator = Validator::make(['province' => '38', 'district' => '1001'], [
        'district' => [Rule::thaiDistrict()->inProvince('province')],
    ], [], ['province' => 'จังหวัด', 'district' => 'อำเภอ']);
    $this->app->setLocale('th');
    $this->assertSame(['อำเภอ ไม่อยู่ในจังหวัดที่เลือก'], $validator->errors()->get('district'));
});

test('an app message for a string rule wins', function () {
    $validator = Validator::make(['district' => '9999'], ['district' => 'thai_district'], ['district.thai_district' => 'Pick a real :attribute.']);
    $this->assertSame(['Pick a real district.'], $validator->errors()->get('district'));
});

test('wildcard parents pair by index', function () {
    $data = ['addresses' => [
        ['province' => '10', 'district' => '1001'],
        ['province' => '38', 'district' => '1001'],
    ]];
    $errors = Validator::make($data, ['addresses.*.district' => [Rule::thaiDistrict()->inProvince('addresses.*.province')]])->errors()->toArray();
    $this->assertSame(['addresses.1.district'], array_keys($errors));

    $errors = Validator::make($data, ['addresses.*.district' => 'thai_district:addresses.*.province'])->errors()->toArray();
    $this->assertSame(['addresses.1.district'], array_keys($errors));
    // Review #3: :other names this row's parent, not the wildcard pattern.
    $this->assertSame(['The addresses.1.district is not in the selected addresses.1.province.'], $errors['addresses.1.district']);

    $errors = Validator::make($data, ['addresses.*.district' => [Rule::thaiDistrict()->inProvince('addresses.*.province')]])->errors()->toArray();
    $this->assertSame(['The addresses.1.district is not in the selected addresses.1.province.'], $errors['addresses.1.district']);
});

test('rule classes work without the macros', function () {
    $validator = Validator::make(VALID, [
        'province' => [new ThaiProvince()],
        'district' => [ThaiDistrict::make()->inProvince('province')],
        'subdistrict' => [ThaiSubdistrict::make()->inDistrict('district')],
        'postal_code' => [ThaiPostalCode::make()->forSubdistrict('subdistrict')],
    ]);
    $this->assertTrue($validator->passes());
});

test('a subdistrict without known postal codes accepts any valid one', function () {
    $none = Thailand::subdistricts()->first(fn ($s) => $s->postalCodes === []);
    $this->assertNotNull($none);
    $validator = Validator::make(['subdistrict' => $none->code, 'postal_code' => '10200'], ['postal_code' => 'thai_postal_code:subdistrict']);
    $this->assertTrue($validator->passes());
});

test('non scalar values fail cleanly', function () {
    $validator = Validator::make(['province' => ['10']], ['province' => 'thai_province']);
    $this->assertSame(['The province must be a valid Thai province code.'], $validator->errors()->get('province'));
});

/*
 * #20: Laravel escapes a dot inside an array key with a placeholder, then hands
 * an object rule the unescaped attribute ("addresses.a.b.district") and the
 * escaped data. Splitting the attribute on "." paired the wildcard with "a",
 * found no parent, and passed. Every parent-aware rule must still check the
 * row it belongs to, whatever its key.
 */
dataset('row keys', [
    'numeric key' => ['0'],
    'dotted key' => ['a.b'],
    'two dots' => ['x.y.z'],
]);

/** @return array<string, array<string, list<mixed>|string>> */
function wildcardRules(): array
{
    return [
        'object' => [
            'addresses.*.district' => [Rule::thaiDistrict()->inProvince('addresses.*.province')],
            'addresses.*.subdistrict' => [Rule::thaiSubdistrict()->inDistrict('addresses.*.district')],
            'addresses.*.postal_code' => [Rule::thaiPostalCode()->forSubdistrict('addresses.*.subdistrict')],
        ],
        'string' => [
            'addresses.*.district' => 'thai_district:addresses.*.province',
            'addresses.*.subdistrict' => 'thai_subdistrict:addresses.*.district',
            'addresses.*.postal_code' => 'thai_postal_code:addresses.*.subdistrict',
        ],
    ];
}

test('a wildcard parent is checked whatever the row key', function (string $style, string $key) {
    // Each level is a real code that does not belong to its parent.
    $bad = Validator::make(['addresses' => [$key => ['province' => '10', 'district' => '5001']]], [
        'addresses.*.district' => wildcardRules()[$style]['addresses.*.district'],
    ]);
    $this->assertTrue($bad->fails(), "district 5001 passed under province 10 with row key {$key}");
    $this->assertSame(
        ["The addresses.{$key}.district is not in the selected addresses.{$key}.province."],
        $bad->errors()->first("addresses.{$key}.district") === '' ? [] : [$bad->errors()->first("addresses.{$key}.district")],
    );

    $bad = Validator::make(['addresses' => [$key => ['district' => '1001', 'subdistrict' => '500101']]], [
        'addresses.*.subdistrict' => wildcardRules()[$style]['addresses.*.subdistrict'],
    ]);
    $this->assertTrue($bad->fails(), "subdistrict 500101 passed under district 1001 with row key {$key}");

    $bad = Validator::make(['addresses' => [$key => ['subdistrict' => '100101', 'postal_code' => '50200']]], [
        'addresses.*.postal_code' => wildcardRules()[$style]['addresses.*.postal_code'],
    ]);
    $this->assertTrue($bad->fails(), "postal code 50200 passed for subdistrict 100101 with row key {$key}");

    $good = Validator::make(['addresses' => [$key => VALID]], wildcardRules()[$style]);
    $this->assertSame([], $good->errors()->toArray(), "a consistent address failed with row key {$key}");
})->with('rule styles')->with('row keys');

test('a wildcard parent that cannot be found fails closed', function (string $style) {
    // No province in the row at all: the district cannot be checked against it.
    $validator = Validator::make(['addresses' => [['district' => '5001']]], [
        'addresses.*.district' => wildcardRules()[$style]['addresses.*.district'],
    ]);
    $this->assertTrue($validator->fails());

    // A present but empty parent still defers to the parent's own rule.
    $validator = Validator::make(['addresses' => [['province' => '', 'district' => '5001']]], [
        'addresses.*.district' => wildcardRules()[$style]['addresses.*.district'],
    ]);
    $this->assertFalse($validator->fails());
})->with('rule styles');
