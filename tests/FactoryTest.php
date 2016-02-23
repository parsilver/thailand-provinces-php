<?php


use PA\ProvinceTh\Factory;


class FactoryTest extends PHPUnit_Framework_TestCase {

    /**
     * @var Factory
     */
    private $factory;


    public function setUp()
    {
        parent::setUp();

        $this->factory = new Factory();
    }


    public function testProvinceData()
    {
        $data = $this->factory->province();

        $this->assertInstanceOf(Illuminate\Support\Collection::class, $data);

        $this->assertCount(10, $data->take(10));
    }

}