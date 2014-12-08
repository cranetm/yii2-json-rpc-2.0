<?php

namespace JsonRpc2;

class Helper {

    /**
     * Recursively brings value to type
     * @param $type
     * @param $value
     * @param bool $isNullable
     * @param array $restrictions
     * @throws Exception
     * @return mixed
     */
    public static function bringValueToType($type, $value, $isNullable = false, $restrictions = [])
    {
        if ($isNullable && null === $value)
            return $value;

        $typeParts = explode("[]", $type);
        $type = current($typeParts);
        if (count($typeParts) > 2)
            throw new Exception("Type '$type' is invalid", Exception::INTERNAL_ERROR);

        if (count($typeParts) === 2) {
            if (!is_array($value))
                throw new Exception("Invalid Params", Exception::INVALID_PARAMS);

            foreach ($value as $key=>$childValue) {
                $value[$key] = self::bringValueToType($type, $childValue, $isNullable);
            }
            return $value;
        }

        if (class_exists($type)) {
            if (!is_subclass_of($type, '\\JsonRpc2\\Dto'))
                throw new Exception("Class '$type' MUST be instance of '\\JsonRpc2\\Dto'", Exception::INTERNAL_ERROR);
            return new $type($value);
        } else {
            switch ($type) {
                case "string":
                    $value = (string)$value;
                    self::restrictValue($type, $value, $restrictions);
                    return $value;
                    break;
                case "int":
                    $value = (int)$value;
                    self::restrictValue($type, $value, $restrictions);
                    return $value;
                    break;
                case "float":
                    $value = (float)$value;
                    self::restrictValue($type, $value, $restrictions);
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
     * @param $type
     * @param $value
     * @param $restrictions
     * @throws Exception if value does not belong to restrictions
     */
    private static function restrictValue($type, $value, $restrictions)
    {
        if (!empty($restrictions) && !in_array($value, $restrictions))
            throw new Exception(sprintf("$type value '$value' is not allowed. Allowed values is '%s'", implode("','", $restrictions)), Exception::INVALID_PARAMS);
    }
} 