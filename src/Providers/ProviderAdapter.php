<?php namespace PA\ProvinceTh\Providers;


interface ProviderAdapter {

    /**
     * @return string
     */
    public function getName();


    /**
     * @return \Illuminate\Support\Collection|array
     */
    public function make();

}