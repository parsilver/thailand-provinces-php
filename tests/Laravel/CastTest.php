<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests\Laravel;

use Farzai\ThailandAddress\District;
use Farzai\ThailandAddress\Exceptions\NotFoundException;
use Farzai\ThailandAddress\Laravel\Casts\AsDistrict;
use Farzai\ThailandAddress\Laravel\Casts\AsProvince;
use Farzai\ThailandAddress\Laravel\Casts\AsSubdistrict;
use Farzai\ThailandAddress\Province;
use Farzai\ThailandAddress\Subdistrict;
use Farzai\ThailandAddress\Thailand;
use Illuminate\Database\Eloquent\Model;

function castModel(): Model
{
    return new class () extends Model {
        protected $guarded = [];

        protected $casts = [
            'province_code' => AsProvince::class,
            'district_code' => AsDistrict::class,
            'subdistrict_code' => AsSubdistrict::class,
        ];
    };
}

test('stores codes and reads models', function () {
    $model = castModel();
    $model->province_code = Thailand::province('10');
    $model->district_code = '1001';
    $model->subdistrict_code = 100101;

    $this->assertSame(['province_code' => '10', 'district_code' => '1001', 'subdistrict_code' => '100101'], $model->getAttributes());
    $this->assertInstanceOf(Province::class, $model->province_code);
    $this->assertInstanceOf(District::class, $model->district_code);
    $this->assertInstanceOf(Subdistrict::class, $model->subdistrict_code);
    $this->assertSame('กรุงเทพมหานคร', $model->subdistrict_code->district->province->nameTh);
});

test('stores the code of the model it is given', function () {
    $model = castModel();
    $model->province_code = Thailand::province('38');
    $model->district_code = Thailand::district('3801');
    $model->subdistrict_code = Thailand::subdistrict('380101');
    $this->assertSame(['province_code' => '38', 'district_code' => '3801', 'subdistrict_code' => '380101'], $model->getAttributes());
});

/** Review #2: a stored code that no longer exists reads back as null. */
test('reads an unknown stored code as null', function () {
    $model = castModel()->newFromBuilder(['province_code' => '99', 'district_code' => '9999', 'subdistrict_code' => '999999']);
    $this->assertNull($model->province_code);
    $this->assertNull($model->district_code);
    $this->assertNull($model->subdistrict_code);
});

/** Review #5: an empty string, as a blank form field sends it, clears the column. */
test('empty string clears the column', function () {
    $model = castModel();
    $model->province_code = '';
    $this->assertSame(['province_code' => null], $model->getAttributes());
});

test('reads a row from the database shape', function () {
    $model = castModel()->newFromBuilder(['province_code' => '38', 'district_code' => null, 'subdistrict_code' => '']);
    $this->assertSame('Bueng Kan', $model->province_code->nameEn);
    $this->assertNull($model->district_code);
    $this->assertNull($model->subdistrict_code);
});

test('null clears the column', function () {
    $model = castModel();
    $model->province_code = null;
    $this->assertSame(['province_code' => null], $model->getAttributes());
});

test('rejects an unknown code', function () {
    $this->expectException(NotFoundException::class);
    castModel()->province_code = '99';
});

test('rejects a model of another level', function () {
    $this->expectException(\InvalidArgumentException::class);
    castModel()->province_code = Thailand::district('1001');
});

test('serialises to the model array', function () {
    $model = castModel();
    $model->province_code = '38';
    $this->assertSame(['province_code' => ['code' => '38', 'name_th' => 'บึงกาฬ', 'name_en' => 'Bueng Kan', 'iso_code' => 'TH-38']], $model->toArray());
});
