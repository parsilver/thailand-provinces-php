<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests\Core;

use Farzai\ThailandAddress\AddressRepository;

/**
 * The shipped data against the DOPA ccaatt.xlsx counts it was generated from
 * (data as of 2023-09-01, see data/sources.json).
 */

beforeEach(function () {
    $this->th = new AddressRepository();
});

test('row counts per level', function () {
    $this->assertCount(77, $this->th->provinces());
    $this->assertCount(928, $this->th->districts());
    $this->assertCount(7436, $this->th->subdistricts());
});

test('bangkok has 50 khet and 180 khwaeng', function () {
    $this->assertSame('กรุงเทพมหานคร', $this->th->province('10')?->nameTh);
    $this->assertCount(50, $this->th->districtsOf('10'));

    $khwaeng = 0;
    foreach ($this->th->districtsOf('10') as $khet) {
        $khwaeng += count($this->th->subdistrictsOf($khet));
    }
    $this->assertSame(180, $khwaeng);
    $this->assertCount(180, $this->th->province('10')->subdistricts());
});

test('bueng kan is code 38 with its own districts only', function () {
    $buengKan = $this->th->province('38');
    $this->assertNotNull($buengKan);
    $this->assertSame('บึงกาฬ', $buengKan->nameTh);
    $this->assertSame('Bueng Kan', $buengKan->nameEn);
    $this->assertCount(8, $this->th->districtsOf('38'));
    $this->assertSame('บึงกาฬ', $this->th->subdistrict('380101')?->nameTh);

    // #7: no second copy of a Bueng Kan district under a missing province.
    $this->assertCount(1, $this->th->districts()->where('nameTh', 'เซกา'));
});

test('every child has an existing parent', function () {
    foreach ($this->th->districts() as $district) {
        $this->assertNotNull($this->th->province($district->provinceCode), "district {$district->code}");
    }
    foreach ($this->th->subdistricts() as $subdistrict) {
        $this->assertNotNull($this->th->district($subdistrict->districtCode), "subdistrict {$subdistrict->code}");
        $this->assertSame(substr($subdistrict->districtCode, 0, 2), $subdistrict->provinceCode);
    }
});

test('codes have the dopa shape', function () {
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
});

test('names are clean and complete', function () {
    $all = $this->th->provinces()->concat($this->th->districts())->concat($this->th->subdistricts());
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
});

/** ISO 3166-2:TH, from the iso-codes release pinned in data/sources.json. */
test('every province has its iso 3166 2 code', function () {
    $seen = [];
    foreach ($this->th->provinces() as $province) {
        $this->assertSame('TH-' . $province->code, $province->isoCode);
        $seen[$province->isoCode] = true;
    }
    $this->assertCount(77, $seen);
});

test('galyani vadhana is a chiang mai district', function () {
    $this->assertSame('50', $this->th->district('5025')?->provinceCode);
    $this->assertCount(25, $this->th->districtsOf('50'));
});

test('postal codes are five digit lists', function () {
    $withPostcode = 0;
    foreach ($this->th->subdistricts() as $s) {
        $this->assertIsList($s->postalCodes);
        foreach ($s->postalCodes as $postalCode) {
            $this->assertMatchesRegularExpression('/^\d{5}$/', $postalCode, $s->code);
        }
        $withPostcode += $s->postalCodes === [] ? 0 : 1;
    }
    $this->assertGreaterThanOrEqual(7300, $withPostcode);
});
