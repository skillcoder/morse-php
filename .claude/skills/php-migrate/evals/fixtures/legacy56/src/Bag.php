<?php
// Planted issues: ArrayAccess/Countable methods without return types (8.1),
// `$bag[] = …` reaching offsetSet() with a null offset (8.5).

class Bag implements ArrayAccess, Countable
{
    private $items = array();

    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    public function count()
    {
        return count($this->items);
    }
}
