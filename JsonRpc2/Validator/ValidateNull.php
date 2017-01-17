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
        throw new Exception("@Null for '{$this->value->name}' is deprecated. All values can be NULL by default. Please use @NotNull for required properties.", Exception::INVALID_PARAMS);
    }
}