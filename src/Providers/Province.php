<?php namespace PA\ProvinceTh\Providers;


use PA\ProvinceTh\Data\DataProvider;

class Province implements ProviderAdapter{

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
        return 'จังหวัด';
    }

    /**
     * @return \Illuminate\Support\Collection|array
     */
    public function make()
    {
        return $this->data->getItems(DataProvider::PROVINCE);
    }
}