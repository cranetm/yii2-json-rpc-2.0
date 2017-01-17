<?php
namespace JsonRpc2;

class Exception extends \yii\base\Exception
{
    const PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;

    private $data = null;

    public function __construct($message, $code, $data = null)
    {
        $this->data = $data;
        parent::__construct($message, $code);
    }

    public function getData()
    {
        return $this->data;
    }

    public function toArray() {
        return [
            "code" 	   => $this->getCode(),
            "message"  => $this->getMessage(),
            "data"     => $this->getData(),
        ];
    }
}
