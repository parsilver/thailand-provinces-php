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

final class CastTest extends TestCase
{
    private function model(): Model
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

    public function test_stores_codes_and_reads_models(): void
    {
        $model = $this->model();
        $model->province_code = Thailand::province('10');
        $model->district_code = '1001';
        $model->subdistrict_code = 100101;

        $this->assertSame(['province_code' => '10', 'district_code' => '1001', 'subdistrict_code' => '100101'], $model->getAttributes());
        $this->assertInstanceOf(Province::class, $model->province_code);
        $this->assertInstanceOf(District::class, $model->district_code);
        $this->assertInstanceOf(Subdistrict::class, $model->subdistrict_code);
        $this->assertSame('กรุงเทพมหานคร', $model->subdistrict_code->district->province->nameTh);
    }

    public function test_stores_the_code_of_the_model_it_is_given(): void
    {
        $model = $this->model();
        $model->province_code = Thailand::province('38');
        $model->district_code = Thailand::district('3801');
        $model->subdistrict_code = Thailand::subdistrict('380101');
        $this->assertSame(['province_code' => '38', 'district_code' => '3801', 'subdistrict_code' => '380101'], $model->getAttributes());
    }

    /** Review #2: a stored code that no longer exists reads back as null. */
    public function test_reads_an_unknown_stored_code_as_null(): void
    {
        $model = $this->model()->newFromBuilder(['province_code' => '99', 'district_code' => '9999', 'subdistrict_code' => '999999']);
        $this->assertNull($model->province_code);
        $this->assertNull($model->district_code);
        $this->assertNull($model->subdistrict_code);
    }

    /** Review #5: an empty string, as a blank form field sends it, clears the column. */
    public function test_empty_string_clears_the_column(): void
    {
        $model = $this->model();
        $model->province_code = '';
        $this->assertSame(['province_code' => null], $model->getAttributes());
    }

    public function test_reads_a_row_from_the_database_shape(): void
    {
        $model = $this->model()->newFromBuilder(['province_code' => '38', 'district_code' => null, 'subdistrict_code' => '']);
        $this->assertSame('Bueng Kan', $model->province_code->nameEn);
        $this->assertNull($model->district_code);
        $this->assertNull($model->subdistrict_code);
    }

    public function test_null_clears_the_column(): void
    {
        $model = $this->model();
        $model->province_code = null;
        $this->assertSame(['province_code' => null], $model->getAttributes());
    }

    public function test_rejects_an_unknown_code(): void
    {
        $this->expectException(NotFoundException::class);
        $this->model()->province_code = '99';
    }

    public function test_rejects_a_model_of_another_level(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->model()->province_code = Thailand::district('1001');
    }

    public function test_serialises_to_the_model_array(): void
    {
        $model = $this->model();
        $model->province_code = '38';
        $this->assertSame(['province_code' => ['code' => '38', 'name_th' => 'บึงกาฬ', 'name_en' => 'Bueng Kan', 'iso_code' => 'TH-38']], $model->toArray());
    }
}
