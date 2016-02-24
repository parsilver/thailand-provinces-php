<?php


use PA\ProvinceTh\Factory;


class FactoryTest extends PHPUnit_Framework_TestCase {


    public function testProvinceData()
    {
        $data = Factory::province();

        $this->assertCount(77, $data);
    }

}