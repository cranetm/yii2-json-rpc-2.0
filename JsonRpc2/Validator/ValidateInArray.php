<?php

namespace JsonRpc2\Validator;

use JsonRpc2;
use JsonRpc2\Exception;

class ValidateInArray extends JsonRpc2\Validator
{
    /**
     * Validate value
     * @throws Exception
     */
    protected function validate()
    {
        $type = $this->value->getType();
        preg_match("/(\[(.*)\])/", $this->params, $matches);
        if (!empty($matches) && in_array($type, ["integer", "double", "float", "string"])) {
            eval("\$restrictions = {$matches[1]};");
            if (!is_array($restrictions))
                throw new Exception(get_class($this).": Invalid syntax in {$this->value->name} tag @inArray{$matches[2]}", Exception::INTERNAL_ERROR);

            if (!empty($restrictions) && !in_array($this->value->data, $restrictions))
                $this->throwError(sprintf("For property '{$this->value->name}' value '{$this->value->data}' is not allowed. Allowed values is '%s'", implode("','", $restrictions)));
        }
    }

    protected function getErrorData()
    {
        preg_match("/\[(.*)\]/", $this->params, $matches);
        return [
            "cause" => $this->value->name,
            "value" => $this->value->data,
            "type" => "inArray",
            "restriction" => $matches[1]
        ];
    }
}