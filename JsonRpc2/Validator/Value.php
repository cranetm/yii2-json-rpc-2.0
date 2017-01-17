<?php

namespace JsonRpc2\Validator;

use JsonRpc2\Dto;

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

    /**
     * Return value's name with DTO's class prefix if exists
     * @return string
     */
    public function getFullName()
    {
        if ($this->parent instanceof Dto)
            return get_class($this->parent) . '::$' . $this->name;
        elseif ('result' === $this->name)
            return 'Result';

        return '$' . $this->name;
    }
}
