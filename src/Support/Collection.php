<?php namespace PA\ProvinceTh\Support;

use ArrayAccess;
use JsonSerializable;


class Collection implements ArrayAccess, JsonSerializable{


    private $items = [];


    public function __construct($items = [])
    {
        $this->setItems($items);
    }


    public function getItems()
    {
        return $this->items;
    }


    public function setItems($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }


    public function each(callable $callback)
    {
        foreach($this->items as $key => $value)
        {
            $callbackResult = $callback($value, $key);

            if($callbackResult === false)
            {
                break;
            }
        }
    }


    public function where($key, $value = null)
    {
        return $this->filter(function($item) use ($key, $value){

            return $item[$key] == $value;
        });
    }


    public function filter(callable $callback = null)
    {
        if ($callback)
        {
            $return = [];
            foreach ($this->items as $key => $value)
            {
                if ($callback($value, $key))
                {
                    $return[$key] = $value;
                }
            }
            return new static(array_values($return));
        }
        return new static(array_filter($this->items));
    }


    public function first()
    {
        return isset($this->items[0]) ? $this->items[0] : null;
    }


    public function count()
    {
        return count($this->items);
    }



    /**
     * @return array
     */
    public function toArray()
    {
        return $this->items;
    }



    private function getArrayableItems($item)
    {
        if(is_array($item))
        {
            return $item;
        }elseif($item instanceof self){
            return $item->toArray();
        }

        return (array)$item;
    }


    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }


    public function offsetGet($offset)
    {
        return isset($this->items[$offset]) ? $this->items[$offset] : null;
    }


    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }


    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->toArray());
    }


    function jsonSerialize()
    {
        return $this->toArray();
    }
}