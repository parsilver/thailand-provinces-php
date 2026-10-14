<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests;

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
        $this->assertSame(['10200'], $subdistrict->postcodes);
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

    public function test_find_by_postcode(): void
    {
        $codes = $this->th->subdistrictsByPostcode('10200')->codes();
        $this->assertContains('100101', $codes);
        foreach ($this->th->subdistrictsByPostcode('10200') as $s) {
            $this->assertContains('10200', $s->postcodes);
        }
        $this->assertCount(0, $this->th->subdistrictsByPostcode('00000'));
        $this->assertCount(0, $this->th->subdistrictsByPostcode('abc'));
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
            ['code' => '100101', 'name_th' => 'พระบรมมหาราชวัง', 'name_en' => 'Phra Borom Maha Ratchawang', 'district_code' => '1001', 'province_code' => '10', 'postcodes' => ['10200']],
            $this->th->subdistrict('100101')?->toArray(),
        );
        $json = json_encode($this->th->districtsOf('38'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"code":"3801"', $json);
    }

    public function test_instances_share_the_loaded_data(): void
    {
        $this->assertSame((new Thailand())->province('10'), (new Thailand())->province('10'));
    }
}
