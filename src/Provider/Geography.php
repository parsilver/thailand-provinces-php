<?php namespace PA\ProvinceTh\Provider;


class Geography implements DataProvider {


    public function getName()
    {
        return 'ภูมิประเทศ';
    }

    
    public function make()
    {
        return $geographies = [
            ['id' => '1', 'name' => 'ภาคเหนือ'],
            ['id' => '2', 'name' => 'ภาคกลาง'],
            ['id' => '3', 'name' => 'ภาคตะวันออกเฉียงเหนือ'],
            ['id' => '4', 'name' => 'ภาคตะวันตก'],
            ['id' => '5', 'name' => 'ภาคตะวันออก'],
            ['id' => '6', 'name' => 'ภาคใต้']
        ];
    }


}