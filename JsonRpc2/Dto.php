<?php
namespace JsonRpc2;


use ReflectionProperty;

class Dto {
    public function __construct($data)
    {
        $this->setDataFromArray($data);
    }

    public function setDataFromArray($data)
    {
        foreach (get_object_vars($this) as $name=>$defaultValue) {
            $property = new ReflectionProperty(get_class($this), $name);
            if (!$property->isPublic()) continue;

            preg_match_all("/@var ([\w\\\\]+)/", $property->getDocComment(), $matches);
            $type = current($matches[1]);
            if (empty($type)) continue;

            $this->$name = $this->bringValueToType($type, isset($data[$name]) ? $data[$name] : $defaultValue);
        }
    }

    /**
     * @param $type
     * @param $value
     * @return array|bool|float|int|string
     * @throws Exception
     */
    private function bringValueToType($type, $value)
    {
        $typeParts = explode("[]", $type);
        $type = current($typeParts);
        if (count($typeParts) > 2)
            throw new Exception("Type '$type' is invalid in ".get_class($this), Exception::INTERNAL_ERROR);

        if (count($typeParts) === 2) {
            if (!is_array($value))
                throw new Exception("Invalid Params in ".get_class($this), Exception::INVALID_PARAMS);

            foreach ($value as $key=>$childValue) {
                $value[$key] = $this->bringValueToType($type, $childValue);
            }
            return $value;
        }

        if (class_exists($type)) {
            if (!is_subclass_of($type, '\\JsonRpc2\\Dto'))
                throw new Exception("Class '$type' MUST be instance of '\\JsonRpc2\\Dto'", Exception::INTERNAL_ERROR);
            $value = new $type($value);
            return $value;
        } else {
            switch ($type) {
                case "string":
                    $value = (string)$value;
                    break;
                case "int":
                    $value = (int)$value;
                    break;
                case "float":
                    $value = (float)$value;
                    break;
                case "array":
                    $value = (array)$value;
                    break;
                case "bool":
                    $value = (bool)$value;
                    break;
            }
            return $value;
        }
    }
}