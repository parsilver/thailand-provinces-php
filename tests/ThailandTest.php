<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests;

use Farzai\ThailandAddress\Address;
use Farzai\ThailandAddress\Collection;
use Farzai\ThailandAddress\District;
use Farzai\ThailandAddress\Province;
use Farzai\ThailandAddress\Subdistrict;
use Farzai\ThailandAddress\Thailand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThailandTest extends TestCase
{
    private Thailand $th;

    protected function setUp(): void
    {
        $this->th = new Thailand();
    }

    public function test_finds_each_level_by_code(): void
    {
        $province = $this->th->province('10');
        $this->assertInstanceOf(Province::class, $province);
        $this->assertSame(['10', 'กรุงเทพมหานคร', 'Bangkok'], [$province->code, $province->nameTh, $province->nameEn]);

        $district = $this->th->district('1001');
        $this->assertInstanceOf(District::class, $district);
        $this->assertSame('10', $district->provinceCode);
        $this->assertSame('Phra Nakhon', $district->nameEn);

        $subdistrict = $this->th->subdistrict('100101');
        $this->assertInstanceOf(Subdistrict::class, $subdistrict);
        $this->assertSame('พระบรมมหาราชวัง', $subdistrict->nameTh);
        $this->assertSame('1001', $subdistrict->districtCode);
        $this->assertSame('10', $subdistrict->provinceCode);
        $this->assertSame(['10200'], $subdistrict->postalCodes);
        $this->assertSame('TH-10', $province->isoCode);
    }

    public function test_accepts_integer_codes(): void
    {
        $this->assertSame('10', $this->th->province(10)?->code);
        $this->assertSame('100101', $this->th->subdistrict(100101)?->code);
    }

    /** @return iterable<string, array{string, int|string}> */
    public static function misses(): iterable
    {
        yield 'unknown province' => ['province', '99'];
        yield 'unknown province as int' => ['province', 999];
        yield 'old 1.x row id' => ['province', 1];
        yield 'empty' => ['district', ''];
        yield 'not a number' => ['district', 'abcd'];
        yield 'wrong length' => ['subdistrict', '1001'];
        yield 'cancelled tambon' => ['subdistrict', '999999'];
    }

    /** #9: a miss is null, never the whole table or a neighbour. */
    #[DataProvider('misses')]
    public function test_find_returns_null_on_a_miss(string $level, int|string $code): void
    {
        $this->assertNull($this->th->{$level}($code));
    }

    public function test_children_of_a_parent(): void
    {
        $districts = $this->th->districtsOf('38');
        $this->assertCount(8, $districts);
        foreach ($districts as $district) {
            $this->assertSame('38', $district->provinceCode);
        }

        $byObject = $this->th->districtsOf($this->th->province('38'));
        $this->assertSame($districts->codes(), $byObject->codes());

        $subdistricts = $this->th->subdistrictsOf('1001');
        $this->assertCount(12, $subdistricts);
        $this->assertSame('100101', $subdistricts->first()?->code);

        $this->assertCount(0, $this->th->districtsOf('99'));
        $this->assertCount(0, $this->th->subdistrictsOf('9999'));
    }

    public function test_search_by_thai_or_english_name(): void
    {
        $this->assertSame(['10'], $this->th->provinces()->search('กรุงเทพ')->codes());
        $this->assertSame(['10'], $this->th->provinces()->search('bangkok')->codes());
        $this->assertSame(['10'], $this->th->provinces()->search('  BANGKOK ')->codes());
        $this->assertContains('3801', $this->th->districts()->search('Bueng Kan')->codes());
        $this->assertCount(0, $this->th->provinces()->search('atlantis'));
        $this->assertCount(0, $this->th->provinces()->search('   '));
    }

    public function test_find_by_postal_code(): void
    {
        $codes = $this->th->subdistrictsByPostalCode('10200')->codes();
        $this->assertContains('100101', $codes);
        foreach ($this->th->subdistrictsByPostalCode('10200') as $s) {
            $this->assertContains('10200', $s->postalCodes);
        }
        $this->assertCount(0, $this->th->subdistrictsByPostalCode('00000'));
        $this->assertCount(0, $this->th->subdistrictsByPostalCode('abc'));
    }

    /** #13: count() and foreach work on every collection, with no deprecation. */
    public function test_collections_are_countable_and_iterable(): void
    {
        $provinces = $this->th->provinces();
        $this->assertInstanceOf(Collection::class, $provinces);
        $this->assertInstanceOf(\Countable::class, $provinces);
        $this->assertCount(77, $provinces);

        $seen = [];
        foreach ($provinces as $province) {
            $this->assertInstanceOf(Province::class, $province);
            $seen[] = $province->code;
        }
        $this->assertCount(77, $seen);
        $this->assertSame($seen, $provinces->codes());
        $this->assertSame($seen, array_map(fn (Province $p) => $p->code, $provinces->all()));
        $this->assertSame('10', $provinces->find('10')?->code);
        $this->assertNull($provinces->find('99'));
    }

    public function test_value_objects_are_read_only(): void
    {
        $province = $this->th->province('10');
        $this->expectException(\Error::class);
        $province->nameTh = 'x';
    }

    public function test_json_shape(): void
    {
        $this->assertSame(
            ['code' => '100101', 'name_th' => 'พระบรมมหาราชวัง', 'name_en' => 'Phra Borom Maha Ratchawang', 'district_code' => '1001', 'province_code' => '10', 'postal_codes' => ['10200']],
            $this->th->subdistrict('100101')?->toArray(),
        );
        $this->assertSame(
            ['code' => '38', 'name_th' => 'บึงกาฬ', 'name_en' => 'Bueng Kan', 'iso_code' => 'TH-38'],
            $this->th->province('38')?->toArray(),
        );
        $this->assertSame(
            ['code' => '3801', 'name_th' => 'เมืองบึงกาฬ', 'name_en' => 'Mueang Bueng Kan', 'province_code' => '38'],
            $this->th->district('3801')?->toArray(),
        );
        $json = json_encode($this->th->districtsOf('38'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"code":"3801"', $json);
    }

    public function test_instances_share_the_loaded_data(): void
    {
        $this->assertSame((new Thailand())->province('10'), (new Thailand())->province('10'));
    }

    public function test_address_uses_the_libaddressinput_field_names(): void
    {
        $address = $this->th->address('100101');
        $this->assertInstanceOf(Address::class, $address);
        $this->assertSame('TH', $address->countryCode);
        $this->assertSame('10200', $address->postalCode);
        $this->assertSame('10', $address->province->code);
        $this->assertSame('1001', $address->district->code);
        $this->assertSame('100101', $address->subdistrict->code);
        $this->assertSame([
            'countryCode' => 'TH',
            'administrativeArea' => 'กรุงเทพมหานคร',
            'locality' => 'เขตพระนคร',
            'dependentLocality' => 'พระบรมมหาราชวัง',
            'postalCode' => '10200',
        ], $address->toArray());
        $this->assertSame([
            'countryCode' => 'TH',
            'administrativeArea' => 'Bangkok',
            'locality' => 'Phra Nakhon',
            'dependentLocality' => 'Phra Borom Maha Ratchawang',
            'postalCode' => '10200',
        ], $address->toArray('en'));
        $this->assertSame($address->toArray(), $address->jsonSerialize());
    }

    public function test_address_postal_code_must_belong_to_the_subdistrict(): void
    {
        $many = $this->th->subdistricts()->filter(fn (Subdistrict $s) => count($s->postalCodes) > 1)->first();
        $this->assertNotNull($many);

        // Ambiguous without a choice: no postal code is guessed.
        $this->assertNull($this->th->address($many->code)?->postalCode);
        $this->assertSame($many->postalCodes[1], $this->th->address($many->code, $many->postalCodes[1])?->postalCode);

        $this->expectException(\InvalidArgumentException::class);
        $this->th->address('100101', '50000');
    }

    public function test_address_misses_and_bad_language(): void
    {
        $this->assertNull($this->th->address('999999'));
        $this->assertNull($this->th->address('1001'));

        $this->expectException(\InvalidArgumentException::class);
        $this->th->address('100101')?->toArray('fr');
    }
}
