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

/*
 * #19: every shipped postal code belongs to its subdistrict's province.
 *
 * A Thai postal code's first two digits name a province's postal area. They
 * are not the DOPA code (Samut Prakan, DOPA 11, delivers on 10xxx). The main
 * prefix of each province below is the one both sources agree on for most of
 * its subdistricts. The few real cross-province codes, where a post office in
 * the next province delivers, are listed one by one. A refresh that brings in
 * a code outside both fails here instead of shipping, as 73697 for 310304
 * did in 2.0.0 and 2.0.1.
 */
const POSTAL_PREFIX_BY_PROVINCE = [
    '10' => '10', '11' => '10', '12' => '11', '13' => '12', '14' => '13', '15' => '14', '16' => '15',
    '17' => '16', '18' => '17', '19' => '18', '20' => '20', '21' => '21', '22' => '22', '23' => '23',
    '24' => '24', '25' => '25', '26' => '26', '27' => '27', '30' => '30', '31' => '31', '32' => '32',
    '33' => '33', '34' => '34', '35' => '35', '36' => '36', '37' => '37', '38' => '38', '39' => '39',
    '40' => '40', '41' => '41', '42' => '42', '43' => '43', '44' => '44', '45' => '45', '46' => '46',
    '47' => '47', '48' => '48', '49' => '49', '50' => '50', '51' => '51', '52' => '52', '53' => '53',
    '54' => '54', '55' => '55', '56' => '56', '57' => '57', '58' => '58', '60' => '60', '61' => '61',
    '62' => '62', '63' => '63', '64' => '64', '65' => '65', '66' => '66', '67' => '67', '70' => '70',
    '71' => '71', '72' => '72', '73' => '73', '74' => '74', '75' => '75', '76' => '76', '77' => '77',
    '80' => '80', '81' => '81', '82' => '82', '83' => '83', '84' => '84', '85' => '85', '86' => '86',
    '90' => '90', '91' => '91', '92' => '92', '93' => '93', '94' => '94', '95' => '95', '96' => '96',
];

/** Subdistrict code => its cross-province postal code. */
const CROSS_PROVINCE_POSTAL_CODES = [
    // Both sources agree.
    '160208' => '18220', // Huai Khun Ram, Lop Buri
    '191105' => '30130', // Lam Somphung, Saraburi
    '191107' => '30130', // Lam Phaya Klang, Saraburi
    '300807' => '36220', // Ban Praeng, Nakhon Ratchasima
    '300818' => '36220', // Nong Sai, Nakhon Ratchasima
    '502501' => '58130', // Ban Chan, Chiang Mai
    '810203' => '80240', // Sinpun, Krabi
    '810206' => '80240', // Khok Han, Krabi
    '820203' => '83000', // Phru Nai, Phang-nga
    // Thailand Post only, kept because both sources put 58130 on 502501: the
    // whole district's one post office is Pai 106 (Ban Chan), a 58130 office.
    '502502' => '58130', // Mae Daet, Chiang Mai
    '502503' => '58130', // Chaem Luang, Chiang Mai
    // Thailand Post only, allowlisted with the evidence in the dataset pipeline
    // (thailand-address-data, src/Build/PostalCodes.php CROSS_PROVINCE).
    '160104' => '13240', // Kong Thanu, Lop Buri: Ban Phraek post office, Ayutthaya
    '210310' => '22160', // Kong Din, Rayong: Na Yai Am post office, Chanthaburi
    '710515' => '70190', // Sanam Yae, Kanchanaburi: Huai Krabok post office, Ratchaburi
];

test('every postal code belongs to its province', function () {
    $this->assertCount(77, POSTAL_PREFIX_BY_PROVINCE);
    $foreign = [];
    foreach ($this->th->subdistricts() as $s) {
        foreach ($s->postalCodes as $postalCode) {
            if (substr($postalCode, 0, 2) !== POSTAL_PREFIX_BY_PROVINCE[$s->provinceCode]
                && (CROSS_PROVINCE_POSTAL_CODES[$s->code] ?? null) !== $postalCode) {
                $foreign[] = "{$s->code} {$s->nameTh}: {$postalCode}";
            }
        }
    }
    $this->assertSame([], $foreign, 'postal codes outside their province');
});

test('known postal codes', function (string $subdistrict, array $postalCodes) {
    $this->assertSame($postalCodes, $this->th->subdistrict($subdistrict)?->postalCodes);
    if (count($postalCodes) === 1) {
        $this->assertSame($postalCodes[0], $this->th->subdistrict($subdistrict)->toAddress()->postalCode);
    }
})->with([
    'Phra Borom Maha Ratchawang, Bangkok' => ['100101', ['10200']],
    'Sung Noen, Buri Ram (was also 73697)' => ['310304', ['31160']],
    'Ban Yuat, Udon Thani (was also 38974)' => ['412003', ['41260']],
    'Song Phi Nong, Chumphon (was also 27408)' => ['860210', ['86140']],
    'Kong Din, Rayong (cross-province, allowlisted)' => ['210310', ['22160']],
]);
