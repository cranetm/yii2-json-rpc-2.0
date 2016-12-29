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

    /**
     * @var Error[]
     */
    private $_errors = array();

    protected $code = -32603; // Set server error code by default
    protected $httpCode = 200;
    protected $message = 'Internal error';


    /**
     * @param Error|Error[] $error
     * @throws
     */
    public function __construct($error = null)
    {
        if (!is_null($error) && !is_array($error)) {
            $error = array($error);
        }
        $this->_errors = $error;
        parent::__construct($this->message, $this->getRPCCode());
    }

    public function getRPCCode()
    {
        return $this->code;
    }

    public function getHTTPStatusCode()
    {
        return $this->httpCode;
    }

    /**
     * @return array
     */
    public function getData()
    {
        if (empty($this->_errors)) {
            return [];
        }
        $errorData = [];
        foreach ($this->_errors as $error) {
            $error = array(
                'code' => $error->getCode(),
                'attribute' => $error->getAttribute(),
                'message' => $error->getMessage(),
                'tpl' => $error->getTpl(),
                'tplParams' => $error->getTplParams(true),
            );
            $errorData[] = $error;
        }
        return $errorData;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->getData();
    }
}