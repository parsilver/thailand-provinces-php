<?php namespace PA\ProvinceTh;


use PA\ProvinceTh\Provider\Province;

class Factory {


    /**
     * Get all province
     *
     * @return array
     */
    public static function province()
    {
        $provider = new Province();

        return $provider->make();
    }

}

