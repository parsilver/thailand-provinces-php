<?php

namespace Farzai\ThailandAddress\Tests;

use PA\ProvinceTh\Factory;

class ProvinceTest extends TestCase
{

    public function testAllProvince()
    {
        $provinces = Factory::province();

        $this->assertCount(77, $provinces->toArray());
    }


    public function testFindProvince()
    {
        $id = 29;
        $shouldSee = 'Udon Thani';
        $geography = 'ภาคตะวันออกเฉียงเหนือ';
        $totalAmphure = 25;

        $amphurMueangUdonthaniId = 421;
        $totalDistrictAmphureMuengUdonthani = 29;

        $province = Factory::province()->find($id);

        $this->assertEquals($shouldSee, $province['name_en']);

        $this->assertEquals($geography, $province->geography()['name']);

        $this->assertEquals($totalAmphure, $province->amphures()->count());

        $this->assertEquals(
            $totalDistrictAmphureMuengUdonthani,
            $province->amphures()->find($amphurMueangUdonthaniId)->districts()->count()
        );
    }
}
