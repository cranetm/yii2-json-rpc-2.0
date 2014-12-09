<?php
namespace JsonRpc2;


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

            preg_match("/@var[ ]+([\w\\\\]+)/", $property->getDocComment(), $matches);
            $type = !empty($matches) ? $matches[1] : false;
            if (empty($type)) continue;

            preg_match("/@null/", $property->getDocComment(), $matches);
            $isNullable = !empty($matches);

            $restrictions = [];
            preg_match("/@(inArray(\[(.*)\]))/", $property->getDocComment(), $matches);
            if (!empty($matches) && in_array($type, ['string', 'int'])) {
                eval("\$parsedData = {$matches[2]};");
                if (!is_array($parsedData))
                    throw new Exception(get_class($this).": Invalid syntax in {$name} tag @inArray{$matches[2]}", Exception::INTERNAL_ERROR);
                $restrictions = $parsedData;
            }
            $this->$name = Helper::bringValueToType($this, $type, isset($data[$name]) ? $data[$name] : $defaultValue, $isNullable, $restrictions);
        }
    }
}