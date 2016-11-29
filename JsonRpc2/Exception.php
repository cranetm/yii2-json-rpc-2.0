<?php
namespace JsonRpc2;

class Exception extends \yii\base\Exception
{
    const PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;
    const SERVER_ERROR = -32000;
    const DATA_NOT_FOUND_ERROR = -32001;
    const EXECUTE_ERROR = -32004;
    const PERMISSION_DENIED_ERROR = -32002;
    const ALREADY_EXECUTE_ERROR = -32003;

    private $data = null;

    protected $code = -32603; // Set server error code by default
    protected $httpCode = 200;
    protected $message = 'Internal error';

    public function __construct($message, $code, $data = null)
    {
        $this->data = $data;
        parent::__construct($message, $code);
    }

    public function toArray() {
        return [
            "code" 	   => $this->getCode(),
            "message"  => $this->getMessage(),
			"data"     => $this->data,
        ];
    }
}