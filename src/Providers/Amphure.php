<?php namespace PA\ProvinceTh\Providers;


use PA\ProvinceTh\Data\DataProvider;
use PA\ProvinceTh\Data\DataWithCollection;

class Amphure implements ProviderAdapter {


    /**
     * @var DataProvider
     */
    private $data;


    public function __construct()
    {
        $this->data = new DataProvider();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'อำเภอ';
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function make()
    {
        return $this->data->getItems(DataProvider::AMPHURE);
    }


}