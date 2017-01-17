<?php

namespace JsonRpc2\Validator;

use JsonRpc2;
use JsonRpc2\Exception;

class ValidateNull extends JsonRpc2\Validator
{
    /**
     * Validate value
     * @throws Exception
     */
    protected function validate()
    {
        throw new Exception(
            \Yii::t('yii',
                '@Null for {valueName} is deprecated. All values can be NULL by default. Please use @NotNull for required properties.',
                ['valueName' => $this->value->getFullName()]
            ),
            Exception::INVALID_PARAMS
        );
    }
}