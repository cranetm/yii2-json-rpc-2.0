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
                throw new Exception(
                    \Yii::t('yii', "{className}: Invalid syntax in {valueName} tag @inArray{restrictions}",
                        [
                            'className' => get_class($this->value->parent),
                            'valueName' => $this->value->getFullName(),
                            'restrictions' => $matches[2],
                        ]
                    ),
                    Exception::INTERNAL_ERROR);

            if (!empty($restrictions) && !in_array($this->value->data, $restrictions))
                $this->throwError(
                    \Yii::t('yii', "Value '{valueData}' is not allowed for {valueName}. Allowed values is '{restrictions}'",
                    [
                        'valueName' => $this->value->getFullName(),
                        'valueData' => $this->value->data,
                        'restrictions' => implode("','", $restrictions),
                    ]
                ));
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