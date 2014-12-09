<?php

namespace JsonRpc2;

class Helper {

    /**
     * Recursively brings value to type
     * @param $parent
     * @param $type
     * @param $value
     * @param bool $isNullable
     * @param array $restrictions
     * @throws Exception
     * @return mixed
     */
    public static function bringValueToType($parent, $type, $value, $isNullable = false, $restrictions = [])
    {
        if ($isNullable && null === $value || empty($type))
            return $value;

        $typeParts = explode("[]", $type);
        $singleType = current($typeParts);
        if (count($typeParts) > 2)
            throw new Exception(sprintf("In %s type '$type' is invalid", get_class($parent)), Exception::INTERNAL_ERROR);

        //for array type
        if (count($typeParts) === 2) {
            if (!is_array($value)) {
                if ($parent instanceof \JsonRpc2\Dto)
                    throw new Exception(sprintf("In %s value has type %s, but array expected", get_class($parent), gettype($value)), Exception::INTERNAL_ERROR);
                else
                    throw new Exception("Value has type %s, but array expected", gettype($value), Exception::INTERNAL_ERROR);
            }

            foreach ($value as $key=>$childValue) {
                $value[$key] = self::bringValueToType($parent, $singleType, $childValue, $isNullable);
            }
            return $value;
        }

        $class = new \ReflectionClass($parent);
        if (0 !== strpos($type, "\\") && class_exists($class->getNamespaceName()."\\".$type)) {
            $type = $class->getNamespaceName()."\\".$type;
        }
        if (class_exists($type)) {
            if (!is_subclass_of($type, '\\JsonRpc2\\Dto'))
                throw new Exception(sprintf("In %s class '%s' MUST be instance of '\\JsonRpc2\\Dto'", get_class($parent), $type), Exception::INTERNAL_ERROR);
            return new $type($value);
        } else {
            switch ($type) {
                case "string":
                    $value = (string)$value;
                    self::restrictValue($parent, $type, $value, $restrictions);
                    return $value;
                    break;
                case "int":
                    $value = (int)$value;
                    self::restrictValue($parent, $type, $value, $restrictions);
                    return $value;
                    break;
                case "float":
                    $value = (float)$value;
                    self::restrictValue($parent, $type, $value, $restrictions);
                    return $value;
                    break;
                case "array":
                    throw new Exception("Parameter type 'array' is deprecated. Use square brackets with simply types or DTO based classes instead.", Exception::INTERNAL_ERROR);
                case "bool":
                    return (bool)$value;
                    break;
            }
        }

        return $value;
    }

    /**
     * @param $parent
     * @param $type
     * @param $value
     * @param $restrictions
     * @throws Exception
     */
    private static function restrictValue($parent, $type, $value, $restrictions)
    {
        if (!empty($restrictions) && !in_array($value, $restrictions)) {
            $message = sprintf("$type value '$value' is not allowed. Allowed values is '%s'", implode("','", $restrictions));
            if ($parent instanceof \JsonRpc2\Dto)
                throw new Exception("In class ".get_class($parent). "" . $message, Exception::INTERNAL_ERROR);
            else
                throw new Exception($message, Exception::INVALID_PARAMS);
        }
    }
} 