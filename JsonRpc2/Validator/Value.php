<?php

namespace JsonRpc2\Validator;

class Value
{
    /**
     * @var string
     */
    public $name;
    /**
     * @var object|array
     */
    public $data;
    /**
     * @var object
     */
    public $parent;

    public function __construct($name, $data, $parent)
    {
        $this->name = $name;
        $this->data = $data;
        $this->parent = $parent;
    }

    /**
     * Return value's type
     * @return string
     */
    public function getType()
    {
        return gettype($this->data);
    }
}