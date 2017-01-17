<?php

namespace JsonRpc2\Validator;

use JsonRpc2;
use JsonRpc2\Exception;

class ValidateNotNull extends JsonRpc2\Validator
{
    /**
     * Validate value
     * @throws Exception
     */
    protected function validate()
    {
        if (null === $this->value->data)
            $this->throwError(
                \Yii::t('yii', "{valueName} is required and cannot be Null.", ['valueName' => $this->value->getFullName()])
            );
    }
}