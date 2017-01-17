<?php

namespace JsonRpc2\Validator;

use JsonRpc2;
use JsonRpc2\Exception;

class ValidateMaxSize extends JsonRpc2\Validator
{
    /**
     * Validate value
     * @throws Exception
     */
    protected function validate()
    {
        $maxSize = (float)(trim($this->params));
        $type = $this->value->getType();
        if ($type === "array" && count($this->value->data) > $maxSize
            || $type === "string" && mb_strlen($this->value->data) > $maxSize
            || in_array($type, ["integer", "double", "float"]) && $this->value->data > $maxSize
        ) {
            $this->throwError(
                \Yii::t('yii', 'For {valueName} allowed max size is {maxSize}',
                    ['valueName' => $this->value->getFullName(), 'maxSize' => $maxSize]
                )
            );

        }
    }
}