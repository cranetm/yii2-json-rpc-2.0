<?php

namespace JsonRpc2;

use JsonRpc2\Validator\Value;
use Yii;
use yii\base\InlineAction;
use yii\base\InvalidParamException;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

class Controller extends \yii\web\Controller
{
    public $enableCsrfValidation = false;

    public $enableResponseValidation = false;

    /**
     * @var string
     */
    public $inlineActionClass = InlineAction::class;

    /** @var array Stores information about param's types and method's return type */
    private $methodInfo = [
        'params' => [],
        'return' => []
    ];

    /** @var \stdClass Contains parsed JSON-RPC 2.0 request object*/
    protected $requestObject;

    public function init()
    {
        parent::init();

        Yii::$app->i18n->translations['jsonrpc'] = ArrayHelper::merge(
            [
                'class' => 'yii\i18n\PhpMessageSource',
                'basePath' => __DIR__. '/../messages',
                'sourceLanguage' => 'en',
            ],
            Yii::$app->i18n->translations['jsonrpc'] ?? []
        );
    }

    public function actionIndex (){}

    /**
     * Validates, runs Action and returns result in JSON-RPC 2.0 format
     * @param string $id the ID of the action to be executed.
     * @param array $params the parameters (name-value pairs) to be passed to the action.
     * @throws \Exception
     * @throws \yii\web\HttpException
     * @return mixed the result of the action.
     * @see createAction()
     */
    public function runAction($id, $params = [])
    {
        $this->initRequest($id);

        try {
            $requestObject = Json::decode(file_get_contents('php://input'), false);
        } catch (InvalidParamException $e) {
            $requestObject = null;
        }
        $isBatch = is_array($requestObject);
        $requests = $isBatch ? $requestObject : [$requestObject];
        $resultData = null;
        if (empty($requests)) {
            $isBatch = false;
            $resultData = [$this->formatResponse(null, new Exception(Yii::t('jsonrpc', 'Invalid Request'), Exception::INVALID_REQUEST))];
        } else {
            foreach ($requests as $request) {
                if($response = $this->getActionResponse($request))
                    $resultData[] = $response;
            }
        }

        $response = Yii::$app->getResponse();
        $response->format = Response::FORMAT_JSON;
        $response->data = $isBatch || null === $resultData ? $resultData : current($resultData);
        return $response;
    }

    /**
     * Runs and returns method response
     * @param $requestObject
     * @throws \Exception
     * @throws \yii\web\HttpException
     * @return Response|array|null
     */
    private function getActionResponse($requestObject)
    {
        $this->requestObject = $result = $error = null;
        try {
            $this->parseAndValidateRequestObject($requestObject);
            ob_start();
            $dirtyResult = parent::runAction($this->requestObject->method);
            ob_clean();
            $result = $this->enableResponseValidation ? $this->validateResult($dirtyResult) : $dirtyResult;
        } catch (HttpException $e) {
            throw $e;
        } catch (Exception $e) {
            if ($e->getCode() === Exception::INVALID_PARAMS) {
                $error = new Exception($e->getMessage(), Exception::INTERNAL_ERROR, $e->getData());
            } else {
                $error = $e;
            }
        } catch (\Exception $e) {
            $error = new Exception(Yii::t('jsonrpc', 'Internal error'), Exception::INTERNAL_ERROR);
        }

        if (!isset($this->requestObject->id) && (empty($error) || !in_array($error->getCode(), [Exception::PARSE_ERROR, Exception::INVALID_REQUEST])))
            return null;

        return $this->formatResponse($result, $error, isset($this->requestObject->id)? $this->requestObject->id : null);
    }

    /**
     * Creates an action based on the given action ID.
     * The method first checks if the action ID has been declared in [[actions()]]. If so,
     * it will use the configuration declared there to create the action object.
     * If not, it will look for a controller method whose name is in the format of `actionXyz`
     * where `Xyz` stands for the action ID. If found, an [[InlineAction]] representing that
     * method will be created and returned.
     * @param string $id the action ID.
     * @throws Exception
     * @return \yii\base\Action the newly created action instance. Null if the ID doesn't resolve into any action.
     */
    public function createAction($id)
    {
        if ($id === '') {
            $id = $this->defaultAction;
        }

        $action = null;
        $actionMap = $this->actions();
        if (isset($actionMap[$id])) {
            $action = Yii::createObject($actionMap[$id], [$id, $this]);
        } elseif (preg_match('/^[a-z0-9\\-_]+$/', $id) && strpos($id, '--') === false && trim($id, '-') === $id) {
            $methodName = 'action' . str_replace(' ', '', ucwords(implode(' ', explode('-', $id))));
            if (method_exists($this, $methodName)) {
                $method = new \ReflectionMethod($this, $methodName);
                if ($method->isPublic() && $method->getName() === $methodName) {
                    // need to check some information
                    $action = new $this->inlineActionClass($id, $this, $methodName);
                }
            }
        }

        if (empty($action))
            throw new Exception(Yii::t('jsonrpc', 'Method not found').' '.$id, Exception::METHOD_NOT_FOUND);

        /** @var \yii\base\Action $action */
        $this->prepareActionParams($action);

        return $action;
    }

    /**
     * Binds the parameters to the action.
     * This method is invoked by [[Action]] when it begins to run with the given parameters.
     * @param \yii\base\Action $action the action to be bound with parameters.
     * @param array $params the parameters to be bound to the action.
     * @throws Exception if params are invalid
     * @return array the valid parameters that the action can run with.
     */
    public function bindActionParams($action, $params)
    {
        try {

            //code from parent
            if ($action instanceof InlineAction) {
                $method = new \ReflectionMethod($this, $action->actionMethod);
            } else {
                $method = new \ReflectionMethod($action, 'run');
            }

            $this->parseMethodDocComment($method);
            $this->validateActionParams();
            $params = $this->requestObject->params;

            $args = [];
            $missing = [];
            $actionParams = [];
            $paramsTypes = $this->getMethodParamsTypes();//changes for array types documented as square brackets

            foreach ($method->getParameters() as $param) {
                $name = $param->getName();
                if (property_exists($params, $name)) {
                    if ($param->isArray() || isset($paramsTypes[$name]) && strpos($paramsTypes[$name], "[]") !== false) { //changes for array types documented as square brackets
                        $args[] = $actionParams[$name] = is_array($params->$name) ? $params->$name : [$params->$name];
                    } elseif (!is_array($params->$name)) {
                        $args[] = $actionParams[$name] = $params->$name;
                    } else {
                        throw new Exception(Yii::t('jsonrpc', 'Invalid data received for parameter "{param}".', [
                            'param' => $name,
                        ]), Exception::INVALID_REQUEST);
                    }
                    unset($params->$name);
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $actionParams[$name] = $param->getDefaultValue();
                } else {
                    $missing[] = $name;
                }
            }

            if (!empty($missing)) {
                throw new Exception(Yii::t('jsonrpc', 'Missing required parameters: {params}', [
                    'params' => implode(', ', $missing),
                ]), Exception::INVALID_REQUEST);
            }

            $this->actionParams = $actionParams;

            return $args;
        } catch (BadRequestHttpException $e) {
            throw new Exception("Invalid Request", Exception::INVALID_REQUEST);
        }
    }

    /**
     * Request has to be sent as POST and with Content-type: application/json
     * @throws \yii\web\HttpException
     */
    private function initRequest($id)
    {
        list($contentType) = explode(";", Yii::$app->request->getContentType()); //cut charset
        $headers = Yii::$app->request->getHeaders();
        if (!empty($id)
            || !Yii::$app->request->getIsOptions() && null !== $headers->get('Origin') // CORS Support
            && (!Yii::$app->request->getIsPost() || empty($contentType) || $contentType != "application/json")
        ) {
            throw new HttpException(404, "Page not found");
        }

        //Call beforeActions on modules and controller to run all filters in behaviors() methods
        $action = parent::createAction('');
        // call beforeAction on modules
        foreach ($this->getModules() as $module) {
            if (!$module->beforeAction($action)) {
                break;
            }
        }
        // call beforeAction on controller
        $this->beforeAction($action);
    }

    /**
     * Try to decode input json data and validate for required fields for JSON-RPC 2.0
     * @param $requestObject string
     * @throws Exception
     */
    private function parseAndValidateRequestObject($requestObject)
    {
        if (null === $requestObject)
            throw new Exception(Yii::t('jsonrpc', 'Parse error'), Exception::PARSE_ERROR);

        if (!is_object($requestObject)
            || !isset($requestObject->jsonrpc) || $requestObject->jsonrpc !== '2.0'
            || empty($requestObject->method) || "string" != gettype($requestObject->method)
        )
            throw new Exception(Yii::t('jsonrpc', 'Invalid Request'), Exception::INVALID_REQUEST);

        $this->requestObject = $requestObject;
        if (!isset($this->requestObject->params))
            $this->requestObject->params = [];
    }

    /**
     * Make associative array where keys are parameters names and values are parameters values
     * @param \yii\base\Action $action
     */
    private function prepareActionParams($action)
    {
        if (is_object($this->requestObject->params))
            return;

        $method = $this->getMethodFromAction($action);
        $methodParams = new \stdClass();

        $i=0;
        foreach ($method->getParameters() as $param) {
            if (!isset($this->requestObject->params[$i])) continue;
            $methodParams->{$param->getName()} = $this->requestObject->params[$i];
            $i++;
        }
        $this->requestObject->params = $methodParams;
    }

    /**
     * Validates and brings method params to its types
     * @throws Exception
     */
    private function validateActionParams()
    {
        foreach ($this->requestObject->params as $name=>$value) {
            if (!isset($this->methodInfo['params'][$name])) continue;
            $paramInfo = $this->methodInfo['params'][$name];

            $paramValue = new Value($paramInfo['name'], $value, $this);
            foreach ($paramInfo['validators'] as $type=>$params) {
                $paramValue = Validator::run($type, $params, $paramValue);
            }
            $this->requestObject->params->$name = $paramValue->data;
        }
    }

    /**
     * Returns reflected method from action
     * @param $action
     * @return \ReflectionMethod
     */
    private function getMethodFromAction($action)
    {
        if ($action instanceof InlineAction) {
            $method = new \ReflectionMethod($this, $action->actionMethod);
            return $method;
        } else {
            $method = new \ReflectionMethod($action, 'run');
            return $method;
        }
    }

    /**
     * Returns method params with types from method phpDoc comments
     * @return array
     */
    private function getMethodParamsTypes()
    {
        return array_reduce($this->methodInfo['params'], function ($result, $item) {
            $result[$item['name']] = $item['validators']['var'];
            return $result;
        }, []);
    }

    /**
     * @param $result
     * @return mixed
     */
    private function validateResult($result)
    {
        if (!empty($this->methodInfo['return'])) {
            $resultValue = new Value($this->methodInfo['return']['name'], $result, $this);
            foreach ($this->methodInfo['return']['validators'] as $type=>$params) {
                $resultValue = Validator::run($type, $params, $resultValue);
            }
            $result = $resultValue->data;
        }

        return $result;
    }

    /**
     * Parses phpDoc comment and fills data in $this->methodInfo
     * @param $method \ReflectionMethod
     * @throws Exception
     */
    private function parseMethodDocComment($method)
    {
        $variableRegex = '\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)';

        $infoTpl = [
            'name' => '',
            'validators' => [],
        ];

        $this->methodInfo = [
            'params' => [],
            'return' => []
        ];

        $lines = preg_split ('/$\R?^/m', $method->getDocComment());
        for ($i=0; $i<count($lines); $i++) {
            preg_match("/@param\s+([\w\\\\\[\]]+)\s+$variableRegex/", $lines[$i], $paramMatches);
            if (!empty($paramMatches)) {
                $subject = &$this->methodInfo['params'][$paramMatches[2]];
                $subject = $infoTpl;
                $subject['name'] = $paramMatches[2];
                $subject['validators']['var'] = $paramMatches[1];
                continue;
            } else {
                preg_match("/@return\s+([\w\\\\\[\]]+)/", $lines[$i], $paramMatches);
                if (!empty($paramMatches)) {
                    $subject = &$this->methodInfo['return'];
                    $subject = $infoTpl;
                    $subject['name'] = "result";
                    $subject['validators']['var'] = $paramMatches[1];
                    continue;
                }
            }
            preg_match("/@([\w]+)[ ]?(.*)/", $lines[$i], $validatorMatches);
            if (!empty($subject) && !empty($validatorMatches)) {
                $subject['validators'][$validatorMatches[1]] = trim($validatorMatches[2]);
            }
        }
    }

    /**
     * Formats and returns
     * @param null $result
     * @param \JsonRpc2\Exception|null $error
     * @param null $id
     * @return array
     */
    public function formatResponse($result = null, Exception $error = null, $id = null)
    {
        $resultArray = [
            'jsonrpc' => '2.0',
            'id' => $id,
        ];

        if (!empty($error)) {
            \Yii::error($error, 'jsonrpc');
            $resultArray['error'] = $error->toArray();
        } else {
            $resultArray['result'] = $result;
        }

        return $resultArray;
    }
}
