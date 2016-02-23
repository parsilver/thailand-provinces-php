<?php namespace PA\ProvinceTh\Data;

class DataProvider {

    const PROVINCE  = 'provinces';

    const AMPHURE   = 'amphures';


    /**
     * @var array|mixed
     */
    private $items = [];


    public function __construct()
    {
        $this->items = require_once PA_BASE_PATH . 'provinces.php';
    }

    /**
     * @param null $key
     * @return array|\Illuminate\Support\Collection
     */
    public function getItems($key = null)
    {
        if(is_null($key))
        {
            return $this->toCollection($this->items);
        }

        return $this->toCollection(isset($this->items[$key]) ? $this->items[$key] : []);
    }


    /**
     * @param array $data
     * @return \Illuminate\Support\Collection
     */
    public function toCollection(array $data)
    {
        return collect($data);
    }

}