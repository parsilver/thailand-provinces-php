<?php

namespace PA\ProvinceTh;

use PA\ProvinceTh\Provider\Amphure;
use PA\ProvinceTh\Provider\District;
use PA\ProvinceTh\Provider\Geography;
use PA\ProvinceTh\Provider\Province;

class Factory
{

    /**
     * @return Geography
     */
    public static function geography()
    {
        return new Geography();
    }


    /**
     * @return Province
     */
    public static function province()
    {
        return new Province();
    }

    /**
     * @return Amphure
     */
    public static function amphure()
    {
        return new Amphure();
    }

    /**
     * @return District
     */
    public static function district()
    {
        return new District();
    }
}
