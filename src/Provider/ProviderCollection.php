<?php namespace PA\ProvinceTh\Provider;

use PA\ProvinceTh\Support\Collection;

abstract class ProviderCollection extends Collection {


    abstract public function data();


    protected $primaryKey = 'id';


    public function __construct($data = null)
    {
        parent::__construct(is_null($data) ? $this->data() : $data);
    }


    public function find($id)
    {
        return new static($this->where($this->primaryKey, $id)->first());
    }


    public function each(callable $callback)
    {
        foreach($this->getItems() as $key => $value)
        {
            $callbackResult = $callback(new static($value), $key);

            if($callbackResult === false)
            {
                break;
            }
        }
    }



    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }


    /**
     * @param $provider
     * @param string $foreignKey
     * @return ProviderCollection
     */
    protected function hasMany($provider, $foreignKey = null)
    {
        if($this->itemIsObject())
        {
            $this->setItems([$this->getItems()]);
        }

        $provider = new $provider();

        if(is_null($foreignKey))
        {
            $foreignKey = strtolower($this->getClassName($this)) . '_id';
        }

        return $provider->where($foreignKey, $this->first()[$this->getPrimaryKey()]);
    }


    /**
     * @param $provider
     * @param $localForeignKey
     * @return array
     */
    protected function belongsTo($provider, $localForeignKey = null)
    {
        if($this->itemIsObject())
        {
            $this->setItems([$this->getItems()]);
        }

        $provider = new $provider();

        if(is_null($localForeignKey))
        {
            $localForeignKey = strtolower($this->getClassName($provider)) . '_id';
        }

        return $provider->where(
            $provider->getPrimaryKey(),
            $this->first()[$localForeignKey]
        )->first();
    }


    protected function getClassName($object)
    {
        $className = get_class($object);
        return (substr($className, strrpos($className, '\\') + 1));
    }


    private function itemIsObject()
    {
        return isset($this->getItems()[$this->getPrimaryKey()]);
    }
}