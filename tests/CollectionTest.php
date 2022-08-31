<?php

namespace Farzai\ThailandAddress\Tests;

use PA\ProvinceTh\Support\Collection;

class CollectionTest extends TestCase
{


    public function testWhere()
    {
        $itemBefore = [
            ['id' => 1, 'name' => 'Udonthani'],
            ['id' => 2, 'name' => 'Bangkok'],
        ];
        $itemAfter = [
            ['id' => 1, 'name' => 'Udonthani']
        ];

        $collection = new Collection($itemBefore);
        $result = $collection->where('id', 1);

        $this->assertInstanceOf(Collection::class, $result);

        $this->assertEquals($itemAfter, $result->toArray());
    }

    public function testWhereFirst()
    {
        $itemBefore = [
            ['id' => 1, 'name' => 'Udonthani'],
            ['id' => 2, 'name' => 'Bangkok'],
        ];
        $itemAfter = ['id' => 2, 'name' => 'Bangkok'];

        $collection = new Collection($itemBefore);
        $result = $collection->where('id', 2)->first();

        $this->assertTrue(is_array($result));
        $this->assertEquals($itemAfter, $result);
    }

    public function testFilter()
    {
        $itemBefore = [
            ['id' => 1, 'name' => 'Udonthani'],
            ['id' => 2, 'name' => 'Bangkok'],
        ];
        $itemAfter = [
            ['id' => 2, 'name' => 'Bangkok']
        ];

        $collection = new Collection($itemBefore);
        $result = $collection->filter(function ($item) {
            return $item['id'] == 2;
        });

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals($itemAfter, $result->toArray());
    }


    public function testForEach()
    {
        $stopValue = 3;
        $loop = 0;
        $shouldStopAtLoop = 3;


        $items = [
            'a' => 1,
            'b' => 2,
            'c' => 3, // It should stop from this loop
            'd' => 4
        ];

        $collection = new Collection($items);

        $collection->each(function ($value) use (&$loop, $stopValue) {

            $loop++;

            if ($value == $stopValue) {
                return false;
            }
        });

        $this->assertEquals($shouldStopAtLoop, $loop);
    }
}
