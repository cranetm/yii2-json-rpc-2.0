<?php

namespace JsonRpc2;

use JsonRpc2;
use JsonRpc2\Validator\Value;
use JsonRpc2\Exception;

class Validator
{
    /**
     * @var Value
     */
    public $value;

    /**
     * @var object
     */
    protected $parent;

    /**
     * @var array
     */
    protected $params;

    public function __construct($value, $params='')
    {
        $this->value = $value;
        $this->params = $params;
        $this->validate();
    }

    /**
     * Validate value and change $result property
     * @throws Exception
     */
    protected function validate()
    {
        //Must be overridden.
    }

    protected function throwError($message)
    {
        throw new Exception($message, Exception::INVALID_PARAMS, $this->getErrorData());
    }

    protected function getErrorData()
    {
        $classParts = explode("Validator\\Validate", get_class($this));
        if (count($classParts) != 2)
            return null;

        return [
            "cause" => $this->value->name,
            "value" => $this->value->data,
            "type" => lcfirst($classParts[1]),
            "restriction" => $this->params
        ];
    }

    /**
     * @param string $name
     * @param string $params
     * @param \JsonRpc2\Validator\Value $value
     * @return object
     */
    public static function run($name, $params, $value)
    {
        $class = "JsonRpc2\\Validator\\Validate" . ucfirst($name);
        if (!class_exists($class)) {
            return $value;
        }
        /** @var \JsonRpc2\Validator $validator */
        $validator = new $class($value, $params);
        return $validator->value;
    }
}