<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests\Core;

use Farzai\ThailandAddress\Address;
use Farzai\ThailandAddress\AddressRepository;
use Farzai\ThailandAddress\District;
use Farzai\ThailandAddress\Exceptions\NotFoundException;
use Farzai\ThailandAddress\Province;
use Farzai\ThailandAddress\Subdistrict;
use Farzai\ThailandAddress\Thailand;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/** The plain-PHP API, with no Laravel application booted. */

afterEach(function () {
    Thailand::swap(null);
});

test('finds each level by code', function () {
    $province = Thailand::province('10');
    $this->assertInstanceOf(Province::class, $province);
    $this->assertSame(['10', 'กรุงเทพมหานคร', 'Bangkok', 'TH-10'], [$province->code, $province->nameTh, $province->nameEn, $province->isoCode]);

    $district = Thailand::district('1001');
    $this->assertInstanceOf(District::class, $district);
    $this->assertSame(['10', 'Phra Nakhon'], [$district->provinceCode, $district->nameEn]);

    $subdistrict = Thailand::subdistrict('100101');
    $this->assertInstanceOf(Subdistrict::class, $subdistrict);
    $this->assertSame(['พระบรมมหาราชวัง', '1001', '10', ['10200']], [$subdistrict->nameTh, $subdistrict->districtCode, $subdistrict->provinceCode, $subdistrict->postalCodes]);

    $this->assertSame('10', Thailand::province(10)?->code);
    $this->assertSame('100101', Thailand::subdistrict(100101)?->code);
});

/** #9: a miss is null, never the whole table or a neighbour. */
test('find returns null on a miss', function (string $level, int|string|null $code) {
    $this->assertNull(Thailand::{$level}($code));
})->with(function () {
    yield 'unknown province' => ['province', '99'];
    yield 'unknown province as int' => ['province', 999];
    yield 'old 1.x row id' => ['province', 1];
    yield 'null' => ['province', null];
    yield 'empty' => ['district', ''];
    yield 'not a number' => ['district', 'abcd'];
    yield 'wrong length' => ['subdistrict', '1001'];
    yield 'unknown subdistrict' => ['subdistrict', '999999'];
});

test('or fail throws on a miss', function () {
    $this->assertSame('10', Thailand::provinceOrFail('10')->code);
    $this->assertSame('1001', Thailand::districtOrFail('1001')->code);
    $this->assertSame('100101', Thailand::subdistrictOrFail('100101')->code);

    $this->expectException(NotFoundException::class);
    $this->expectExceptionMessage('No district has the code "9999".');
    Thailand::districtOrFail('9999');
});

test('collections are laravel collections', function () {
    $provinces = Thailand::provinces();
    $this->assertInstanceOf(Collection::class, $provinces);
    $this->assertCount(77, $provinces);
    $this->assertTrue(array_is_list($provinces->all()));
    $this->assertSame('10', $provinces->firstWhere('nameEn', 'Bangkok')?->code);
    $this->assertSame('Bangkok', $provinces->where('code', '10')->first()?->nameEn);
    $this->assertSame('Amnat Charoen', $provinces->sortBy('nameEn')->first()?->nameEn);
    $this->assertSame(['10', '11'], $provinces->pluck('code')->take(2)->all());
    $this->assertSame(['TH-10' => 'Bangkok'], $provinces->take(1)->mapWithKeys(fn (Province $p) => [$p->isoCode => $p->nameEn])->all());

    $seen = 0;
    foreach (Thailand::districts() as $district) {
        $this->assertInstanceOf(District::class, $district);
        $seen++;
    }
    $this->assertSame(928, $seen);
    $this->assertCount(7436, Thailand::subdistricts());
});

test('children and lazy parents', function () {
    $this->assertCount(8, Thailand::province('38')->districts());
    $this->assertSame(['1001'], Thailand::province('10')->districts()->pluck('code')->take(1)->all());
    $this->assertCount(12, Thailand::district('1001')->subdistricts());
    $this->assertSame('100101', Thailand::district('1001')->subdistricts()->first()?->code);

    $subdistrict = Thailand::subdistrict('100101');
    $this->assertSame('กรุงเทพมหานคร', $subdistrict->district->province->nameTh);
    $this->assertSame('เขตพระนคร', $subdistrict->district->nameTh);
    $this->assertSame('Bangkok', $subdistrict->province->nameEn);
    $this->assertSame($subdistrict->district(), $subdistrict->district);
    $this->assertTrue(isset($subdistrict->district));
    $this->assertSame('10', Thailand::district('1001')->province->code);
});

test('postal code lookup', function () {
    $subdistricts = Thailand::postalCode('10200');
    $this->assertContains('100101', $subdistricts->pluck('code')->all());
    $subdistricts->each(fn (Subdistrict $s) => $this->assertContains('10200', $s->postalCodes));
    $this->assertCount(0, Thailand::postalCode('00000'));
    $this->assertCount(0, Thailand::postalCode('abc'));
});

test('search covers every level in thai and english', function () {
    $bangRak = Thailand::search('บางรัก');
    $this->assertContains('1004', $bangRak->pluck('code')->all());
    $this->assertContainsOnlyInstancesOf(District::class, $bangRak->filter(fn ($m) => strlen($m->code) === 4)->all());

    $bangkok = Thailand::search('  BANGKOK ');
    $this->assertInstanceOf(Province::class, $bangkok->first());
    $this->assertSame('10', $bangkok->first()->code);

    $levels = Thailand::search('Bueng Kan')->map(fn ($m) => $m::class)->unique()->values()->all();
    $this->assertSame([Province::class, District::class, Subdistrict::class], $levels);

    $this->assertCount(0, Thailand::search('atlantis'));
    $this->assertCount(0, Thailand::search('   '));
});

test('to address uses the libaddressinput names', function () {
    $address = Thailand::subdistrict('100101')->toAddress();
    $this->assertInstanceOf(Address::class, $address);
    $this->assertSame(['TH', 'กรุงเทพมหานคร', 'เขตพระนคร', 'พระบรมมหาราชวัง', '10200'], [
        $address->countryCode, $address->administrativeArea, $address->locality, $address->dependentLocality, $address->postalCode,
    ]);
    $this->assertSame([
        'countryCode' => 'TH',
        'administrativeArea' => 'Bangkok',
        'locality' => 'Phra Nakhon',
        'dependentLocality' => 'Phra Borom Maha Ratchawang',
        'postalCode' => '10200',
    ], $address->inLocale('en')->toArray());
    $this->assertSame($address->toArray(), $address->jsonSerialize());
    $this->assertSame('Bangkok', Thailand::subdistrict('100101')->toAddress(locale: 'en')->administrativeArea);
});

test('to address never guesses a postal code', function () {
    $many = Thailand::subdistricts()->first(fn (Subdistrict $s) => count($s->postalCodes) > 1);
    $this->assertNotNull($many);
    $this->assertNull($many->toAddress()->postalCode);
    $this->assertSame($many->postalCodes[1], $many->toAddress($many->postalCodes[1])->postalCode);

    $this->expectException(\InvalidArgumentException::class);
    Thailand::subdistrict('100101')->toAddress('50000');
});

test('models are arrayable json and read only', function () {
    $province = Thailand::province('38');
    $this->assertInstanceOf(Arrayable::class, $province);
    $this->assertSame(['code' => '38', 'name_th' => 'บึงกาฬ', 'name_en' => 'Bueng Kan', 'iso_code' => 'TH-38'], $province->toArray());
    $this->assertSame(['code' => '3801', 'name_th' => 'เมืองบึงกาฬ', 'name_en' => 'Mueang Bueng Kan', 'province_code' => '38'], Thailand::district('3801')->toArray());
    $this->assertSame(
        ['code' => '100101', 'name_th' => 'พระบรมมหาราชวัง', 'name_en' => 'Phra Borom Maha Ratchawang', 'district_code' => '1001', 'province_code' => '10', 'postal_codes' => ['10200']],
        Thailand::subdistrict('100101')->toArray(),
    );
    $json = json_encode(Thailand::province('38')->districts(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $this->assertStringStartsWith('[{"code":"3801"', $json);
    $this->assertSame($province->toArray(), json_decode($province->toJson(), true));

    $this->expectException(\Error::class);
    $province->nameTh = 'x';
});

test('unknown property is an error not null', function () {
    $this->expectException(\LogicException::class);
    Thailand::province('10')->district; // provinces have no parent
});

test('the facade can be swapped for a fake', function () {
    $fake = AddressRepository::fromArrays(
        ['99' => ['name_th' => 'ทดสอบ', 'name_en' => 'Test', 'iso_code' => 'TH-99']],
        ['9901' => ['name_th' => 'เมืองทดสอบ', 'name_en' => 'Mueang Test']],
        ['990101' => ['name_th' => 'ในเมือง', 'name_en' => 'Nai Mueang', 'postal_codes' => ['99000']]],
    );
    Thailand::swap($fake);

    $this->assertSame($fake, Thailand::repository());
    $this->assertCount(1, Thailand::provinces());
    $this->assertNull(Thailand::province('10'));
    $this->assertSame('Test', Thailand::subdistrict('990101')->district->province->nameEn);
    $this->assertSame(['990101'], Thailand::postalCode('99000')->pluck('code')->all());

    Thailand::swap(null);
    $this->assertCount(77, Thailand::provinces());
});

test('repositories share the loaded rows but not models', function () {
    $a = new AddressRepository();
    $this->assertSame($a->province('10'), $a->province('10'));
    $this->assertEquals((new AddressRepository())->province('10')->toArray(), $a->province('10')->toArray());
});

/** Review #1: a model serializes as its code, not the whole repository. */
test('models serialize by code', function () {
    foreach ([Thailand::province('10'), Thailand::district('1001'), Thailand::subdistrict('100101')] as $model) {
        $bytes = serialize($model);
        $this->assertLessThan(200, strlen($bytes), $model::class . ' serialized to ' . strlen($bytes) . ' bytes');
        $back = unserialize($bytes);
        $this->assertInstanceOf($model::class, $back);
        $this->assertSame($model->toArray(), $back->toArray());
    }
    $back = unserialize(serialize(Thailand::subdistrict('100101')));
    $this->assertSame('กรุงเทพมหานคร', $back->district->province->nameTh);
});

test('debug info is the model not the repository', function () {
    $this->assertTrue(method_exists(Thailand::province('38'), '__debugInfo'), 'var_dump/print_r would walk the whole repository');
    $this->assertSame(Thailand::province('38')->toArray(), Thailand::province('38')->__debugInfo());
});
