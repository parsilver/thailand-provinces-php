<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests;

use Farzai\ThailandAddress\Thailand;
use PHPUnit\Framework\TestCase;

/**
 * The shipped data against the DOPA ccaatt.xlsx counts it was generated from
 * (data as of 2023-09-01, see data/sources.json).
 */
final class DataIntegrityTest extends TestCase
{
    private Thailand $th;

    protected function setUp(): void
    {
        $this->th = new Thailand();
    }

    public function test_row_counts_per_level(): void
    {
        $this->assertCount(77, $this->th->provinces());
        $this->assertCount(928, $this->th->districts());
        $this->assertCount(7436, $this->th->subdistricts());
    }

    public function test_bangkok_has_50_khet_and_180_khwaeng(): void
    {
        $this->assertSame('กรุงเทพมหานคร', $this->th->province('10')?->nameTh);
        $this->assertCount(50, $this->th->districtsOf('10'));

        $khwaeng = 0;
        foreach ($this->th->districtsOf('10') as $khet) {
            $khwaeng += count($this->th->subdistrictsOf($khet));
        }
        $this->assertSame(180, $khwaeng);
    }

    public function test_bueng_kan_is_code_38_with_its_own_districts_only(): void
    {
        $buengKan = $this->th->province('38');
        $this->assertNotNull($buengKan);
        $this->assertSame('บึงกาฬ', $buengKan->nameTh);
        $this->assertSame('Bueng Kan', $buengKan->nameEn);
        $this->assertCount(8, $this->th->districtsOf('38'));
        $this->assertSame('บึงกาฬ', $this->th->subdistrict('380101')?->nameTh);

        // #7: no second copy of a Bueng Kan district under a missing province.
        $this->assertCount(1, $this->th->districts()->filter(fn ($d) => $d->nameTh === 'เซกา'));
    }

    public function test_every_child_has_an_existing_parent(): void
    {
        foreach ($this->th->districts() as $district) {
            $this->assertNotNull($this->th->province($district->provinceCode), "district {$district->code}");
        }
        foreach ($this->th->subdistricts() as $subdistrict) {
            $this->assertNotNull($this->th->district($subdistrict->districtCode), "subdistrict {$subdistrict->code}");
            $this->assertSame(substr($subdistrict->districtCode, 0, 2), $subdistrict->provinceCode);
        }
    }

    public function test_codes_have_the_dopa_shape(): void
    {
        foreach ($this->th->provinces() as $p) {
            $this->assertMatchesRegularExpression('/^[1-9]\d$/', $p->code);
        }
        foreach ($this->th->districts() as $d) {
            $this->assertMatchesRegularExpression('/^\d{4}$/', $d->code);
            $this->assertStringStartsWith($d->provinceCode, $d->code);
        }
        foreach ($this->th->subdistricts() as $s) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $s->code);
            $this->assertStringStartsWith($s->districtCode, $s->code);
        }
    }

    public function test_names_are_clean_and_complete(): void
    {
        $all = [...$this->th->provinces(), ...$this->th->districts(), ...$this->th->subdistricts()];
        foreach ($all as $row) {
            foreach ([$row->nameTh, $row->nameEn] as $name) {
                $this->assertNotSame('', $name, "empty name on {$row->code}");
                // #6: cancelled rows (marked with *) are not shipped.
                $this->assertStringNotContainsString('*', $name, $row->code);
                // #16: no stray, doubled or trailing whitespace.
                $this->assertSame(trim($name), $name, $row->code);
                $this->assertDoesNotMatchRegularExpression('/\s{2}|\t/u', $name, $row->code);
                $this->assertTrue(mb_check_encoding($name, 'UTF-8'), $row->code);
            }
        }
    }

    /** ISO 3166-2:TH, from the iso-codes release pinned in data/sources.json. */
    public function test_every_province_has_its_iso_3166_2_code(): void
    {
        $seen = [];
        foreach ($this->th->provinces() as $province) {
            $this->assertSame('TH-' . $province->code, $province->isoCode);
            $seen[$province->isoCode] = true;
        }
        $this->assertCount(77, $seen);
    }

    public function test_galyani_vadhana_is_a_chiang_mai_district(): void
    {
        $this->assertSame('50', $this->th->district('5025')?->provinceCode);
        $this->assertCount(25, $this->th->districtsOf('50'));
    }

    public function test_postal_codes_are_five_digit_lists(): void
    {
        $withPostcode = 0;
        foreach ($this->th->subdistricts() as $s) {
            $this->assertIsList($s->postalCodes);
            foreach ($s->postalCodes as $postalCode) {
                $this->assertMatchesRegularExpression('/^\d{5}$/', $postalCode, $s->code);
            }
            $withPostcode += $s->postalCodes === [] ? 0 : 1;
        }
        $this->assertGreaterThanOrEqual(7300, $withPostcode);
    }
}
