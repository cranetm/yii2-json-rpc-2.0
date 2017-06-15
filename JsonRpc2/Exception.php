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


    /**
     * @var array|\common\components\Error|\common\components\Error[]|null
     */
    private $_errors = array();

    protected $code = -32603; // Set server error code by default
    protected $httpCode = 200;
    protected $message = 'Internal error';

    /**
     * Exception constructor.
     * @param string|array|\common\components\Error|\common\components\Error[]|null $error
     * @param null $code
     */
    public function __construct($error = null, $code = null)
    {
        if (is_null($code)) {
            $code = $this->code;
        }
        if (!is_null($error) && !is_array($error)) {
            $error = array($error);
        }
        $this->_errors = $error;
        parent::__construct($this->message, $code);
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
            if (!($error instanceof \common\components\Error)) {
                $newError = new \common\components\Error(
                    \common\components\Code::C_EXCEPTION_MESSAGE,
                    null,
                    ['{message}' => $error]);
                $newError->setCode($this->getCode());
                $error = $newError;
            }

            $error = [
                'code' => $error->getCode(),
                'attribute' => $error->getAttribute(),
                'message' => $error->getMessage(),
                'tpl' => $error->getTpl(),
                'tplParams' => $error->getTplParams(true),
            ];


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