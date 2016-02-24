<?php namespace PA\ProvinceTh\Provider;


interface DataProvider {

    /**
     * @return string
     */
    public function getName();


    /**
     * @return array
     */
    public function make();

}