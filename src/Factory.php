<?php namespace PA\ProvinceTh;

use PA\ProvinceTh\Providers\Province;


class Factory {


    /**
     * Get all province
     *
     * @return array|\Illuminate\Support\Collection
     */
    public function province()
    {
        $provider = new Province();

        return $provider->make();
    }

}

