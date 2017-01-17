<?php

namespace JsonRpc2\Validator;

use JsonRpc2;
use JsonRpc2\Exception;

class ValidateMinSize extends JsonRpc2\Validator
{
    /**
     * Validate value
     * @throws Exception
     */
    protected function validate()
    {
        $minSize = (float)(trim($this->params));
        $type = $this->value->getType();
        if ($type === "NULL" && $minSize > 0
            || $type === "array" && count($this->value->data) < $minSize
            || $type === "string" && mb_strlen($this->value->data) < $minSize
            || in_array($type, ["integer", "double", "float"]) && $this->value->data < $minSize
        ) {
            $this->throwError(
                \Yii::t('yii', 'For {valueName} allowed min size is {minSize}',
                    ['valueName' => $this->value->getFullName(), 'minSize' => $minSize]
                )
            );
        }
    }
}