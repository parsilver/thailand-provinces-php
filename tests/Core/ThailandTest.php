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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The plain-PHP API, with no Laravel application booted. */
final class ThailandTest extends TestCase
{
    protected function tearDown(): void
    {
        Thailand::swap(null);
    }

    public function test_finds_each_level_by_code(): void
    {
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
    }

    /** @return iterable<string, array{string, int|string|null}> */
    public static function misses(): iterable
    {
        yield 'unknown province' => ['province', '99'];
        yield 'unknown province as int' => ['province', 999];
        yield 'old 1.x row id' => ['province', 1];
        yield 'null' => ['province', null];
        yield 'empty' => ['district', ''];
        yield 'not a number' => ['district', 'abcd'];
        yield 'wrong length' => ['subdistrict', '1001'];
        yield 'unknown subdistrict' => ['subdistrict', '999999'];
    }

    /** #9: a miss is null, never the whole table or a neighbour. */
    #[DataProvider('misses')]
    public function test_find_returns_null_on_a_miss(string $level, int|string|null $code): void
    {
        $this->assertNull(Thailand::{$level}($code));
    }

    public function test_or_fail_throws_on_a_miss(): void
    {
        $this->assertSame('10', Thailand::provinceOrFail('10')->code);
        $this->assertSame('1001', Thailand::districtOrFail('1001')->code);
        $this->assertSame('100101', Thailand::subdistrictOrFail('100101')->code);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No district has the code "9999".');
        Thailand::districtOrFail('9999');
    }

    public function test_collections_are_laravel_collections(): void
    {
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
    }

    public function test_children_and_lazy_parents(): void
    {
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
    }

    public function test_postal_code_lookup(): void
    {
        $subdistricts = Thailand::postalCode('10200');
        $this->assertContains('100101', $subdistricts->pluck('code')->all());
        $subdistricts->each(fn (Subdistrict $s) => $this->assertContains('10200', $s->postalCodes));
        $this->assertCount(0, Thailand::postalCode('00000'));
        $this->assertCount(0, Thailand::postalCode('abc'));
    }

    public function test_search_covers_every_level_in_thai_and_english(): void
    {
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
    }

    public function test_to_address_uses_the_libaddressinput_names(): void
    {
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
    }

    public function test_to_address_never_guesses_a_postal_code(): void
    {
        $many = Thailand::subdistricts()->first(fn (Subdistrict $s) => count($s->postalCodes) > 1);
        $this->assertNotNull($many);
        $this->assertNull($many->toAddress()->postalCode);
        $this->assertSame($many->postalCodes[1], $many->toAddress($many->postalCodes[1])->postalCode);

        $this->expectException(\InvalidArgumentException::class);
        Thailand::subdistrict('100101')->toAddress('50000');
    }

    public function test_models_are_arrayable_json_and_read_only(): void
    {
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
    }

    public function test_unknown_property_is_an_error_not_null(): void
    {
        $this->expectException(\LogicException::class);
        Thailand::province('10')->district; // provinces have no parent
    }

    public function test_the_facade_can_be_swapped_for_a_fake(): void
    {
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
    }

    public function test_repositories_share_the_loaded_rows_but_not_models(): void
    {
        $a = new AddressRepository();
        $this->assertSame($a->province('10'), $a->province('10'));
        $this->assertEquals((new AddressRepository())->province('10')->toArray(), $a->province('10')->toArray());
    }
}
