<?php

use PA\ProvinceTh\Data\DataProvider;
use PA\ProvinceTh\Data\DataWithCollection;

class DataProviderTest extends PHPUnit_Framework_TestCase {


    public function testMakeProvider()
    {
        $data = new DataProvider();
        $items = $data->getItems(DataProvider::PROVINCE);

        $isArray = is_array($items);

        $this->assertFalse($isArray);
        $this->assertInstanceOf(Illuminate\Support\Collection::class, $items);
    }


}