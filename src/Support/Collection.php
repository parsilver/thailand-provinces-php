<?php

namespace PA\ProvinceTh\Support;

use ArrayAccess;
use JsonSerializable;


class Collection implements ArrayAccess, JsonSerializable
{

    /**
     * @var array
     */
    private $items = [];

    /**
     * Collection constructor.
     * @param array $items
     */
    public function __construct($items = [])
    {
        $this->setItems($items);
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param array $items
     */
    public function setItems($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * @param callable $callback
     */
    public function each(callable $callback)
    {
        foreach ($this->items as $key => $value) {
            $callbackResult = $callback($value, $key);

            if ($callbackResult === false) {
                break;
            }
        }
    }

    /**
     * @param $key
     * @param null $value
     * @return $this
     */
    public function where($key, $value = null)
    {
        return $this->filter(function ($item) use ($key, $value) {
            return $item[$key] == $value;
        });
    }

    /**
     * @param callable|null $callback
     * @return $this
     */
    public function filter(callable $callback = null)
    {
        if ($callback) {
            $return = [];
            foreach ($this->items as $key => $value) {
                if ($callback($value, $key)) {
                    $return[$key] = $value;
                }
            }
            return new static(array_values($return));
        }
        return new static(array_filter($this->items));
    }

    /**
     * @return mixed|null
     */
    public function first()
    {
        return isset($this->items[0])
            ? $this->items[0]
            : null;
    }

    /**
     * @return int
     */
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

    /**
     * @param $item
     * @return array
     */
    private function getArrayableItems($item)
    {
        if (is_array($item)) {
            return $item;
        } elseif ($item instanceof self) {
            return $item->toArray();
        }

        return (array)$item;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return isset($this->items[$offset])
            ? $this->items[$offset]
            : null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     */
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

    /**
     * @return array|mixed
     */
    function jsonSerialize()
    {
        return $this->toArray();
    }
}
