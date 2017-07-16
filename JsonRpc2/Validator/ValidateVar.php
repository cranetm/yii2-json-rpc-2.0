<?php

namespace JsonRpc2\Validator;

use JsonRpc2;
use JsonRpc2\Exception;

class ValidateVar extends JsonRpc2\Validator
{
    /**
     * Validate value
     * @throws Exception
     */
    protected function validate()
    {
        $this->value->data = $this->bringValueToType($this->value->parent, trim($this->params), $this->value->data);
    }

    /**
     * Recursively brings value to type
     * @param $parent
     * @param $type
     * @param $value
     * @throws Exception
     * @return mixed
     */
    private function bringValueToType($parent, $type, $value)
    {
        if (null === $value || empty($type))
            return $value;

        $typeParts = explode("[]", $type);
        $singleType = current($typeParts);
        if (count($typeParts) > 2)
            throw new Exception(
                \Yii::t('yii', 'In {className} type \'{type}\' is invalid',
                    ['className' => get_class($parent), 'type' => $type]
                ),
                Exception::INTERNAL_ERROR
            );

        //for array type
        if (count($typeParts) === 2) {
            if (!is_array($value)) {
                if ($parent instanceof \JsonRpc2\Dto)
                    throw new Exception(
                        \Yii::t('yii', 'In {className} value has type \'{type}\', but array expected',
                            ['className' => get_class($parent), 'type' => gettype($value)]
                        ),
                        Exception::INTERNAL_ERROR
                    );
                else
                    throw new Exception(\Yii::t('yii', 'Value has type \'{type}\', but array expected', ['type' => gettype($value)]), Exception::INTERNAL_ERROR);
            }

            foreach ($value as $key=>$childValue) {
                $value[$key] = $this->bringValueToType($parent, $singleType, $childValue);
            }
            return $value;
        }

        $class = new \ReflectionClass($parent);
        if (0 !== strpos($type, "\\") && class_exists($class->getNamespaceName()."\\".$type)) {
            $type = $class->getNamespaceName()."\\".$type;
        }
        if (class_exists($type)) {
            if (!is_subclass_of($type, '\\JsonRpc2\\Dto'))
                throw new Exception(
                    \Yii::t('yii', 'In {className} class \'{type}\' MUST be instance of \'\\JsonRpc2\\Dto\'',
                        ['className' => get_class($parent), 'type' => $type]
                    ),
                    Exception::INTERNAL_ERROR
                );
            return new $type($value);
        } else {
            switch ($type) {
                case "string":
                    return (string)$value;
                case "int":
                    return (int)$value;
                case "float":
                    return (float)$value;
                case "double":
                    return (double)$value;
                case "mixed":
                case "array":
                    return (array)$value;
                case "bool":
                    return (bool)$value;
            }
        }

        return $value;
    }
}
