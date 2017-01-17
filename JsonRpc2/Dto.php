<?php
namespace JsonRpc2;


use JsonRpc2\Validator\Value;
use ReflectionProperty;

class Dto {
    public function __construct($data)
    {
        $this->setDataFromArray((array)$data);
    }

    protected function setDataFromArray($data)
    {
        foreach (get_object_vars($this) as $name=>$defaultValue) {
            $property = new ReflectionProperty(get_class($this), $name);
            if (!$property->isPublic()) continue;
            preg_match_all("/@([\w]+)[ ]?(.*)/", $property->getDocComment(), $matches);
            $propValue = new Value($name, isset($data[$name]) ? $data[$name] : $defaultValue, $this);
            foreach ($matches[1] as $key=>$value) {
                $propValue = Validator::run($matches[1][$key], trim($matches[2][$key]), $propValue);
            }
            $this->$name = $propValue->data;
        }
    }
}
