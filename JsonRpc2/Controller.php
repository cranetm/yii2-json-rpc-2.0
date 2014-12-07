<?php

namespace JsonRpc2;

use Yii;
use yii\base\InlineAction;
use yii\base\InvalidParamException;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

class Controller extends \yii\web\Controller
{
    public $enableCsrfValidation = false;

    /** @var array Contains parsed JSON-RPC 2.0 request object*/
    private $requestObject;

    /** @var array Use as 'result' when Action returns null */
    private $defaultResult = [
        "success" => true
    ];

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
        $error = null;
        $result = [];
        try {
            $this->initRequest($id);
            $this->parseAndValidateRequestObject();
            ob_start();
            $result = parent::runAction($this->requestObject['method']);
            ob_clean();
        } catch (HttpException $e) {
            throw $e;
        } catch (Exception $e) {
            $error = $e;
        } catch (\Exception $e) {
            $error = new Exception("Internal error", Exception::INTERNAL_ERROR);
        }

        $response = new Response();
        $response->format = Response::FORMAT_JSON;
        if (!isset($this->requestObject['id']) && empty($error))
            return $response;

        $response->data = [
            'jsonrpc' => '2.0',
            'id' => !empty($this->requestObject['id'])? $this->requestObject['id'] : null,
        ];
        if (!empty($result))
            $response->data['result'] = $result;

        if (!empty($error))
            $response->data['error'] = $error->toArray();

        if (empty($result) && empty($error))
            $response->data['result'] = $this->defaultResult;

        return $response;
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
        $action = parent::createAction($id);
        if (empty($action))
            throw new Exception("Method not found", Exception::METHOD_NOT_FOUND);

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
            $this->validateActionParams($action);
            $params = $this->requestObject['params'];

            //code from parent
            if ($action instanceof InlineAction) {
                $method = new \ReflectionMethod($this, $action->actionMethod);
            } else {
                $method = new \ReflectionMethod($action, 'run');
            }

            $args = [];
            $missing = [];
            $actionParams = [];
            $paramsTypes = $this->getMethodParamsTypes($method);//changes for array types documented as square brackets

            foreach ($method->getParameters() as $param) {
                $name = $param->getName();
                if (array_key_exists($name, $params)) {
                    if ($param->isArray() || isset($paramsTypes[$name]) && strpos($paramsTypes[$name], "[]") !== false) { //changes for array types documented as square brackets
                        $args[] = $actionParams[$name] = is_array($params[$name]) ? $params[$name] : [$params[$name]];
                    } elseif (!is_array($params[$name])) {
                        $args[] = $actionParams[$name] = $params[$name];
                    } else {
                        throw new BadRequestHttpException(Yii::t('yii', 'Invalid data received for parameter "{param}".', [
                            'param' => $name,
                        ]));
                    }
                    unset($params[$name]);
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $actionParams[$name] = $param->getDefaultValue();
                } else {
                    $missing[] = $name;
                }
            }

            if (!empty($missing)) {
                throw new BadRequestHttpException(Yii::t('yii', 'Missing required parameters: {params}', [
                    'params' => implode(', ', $missing),
                ]));
            }

            $this->actionParams = $actionParams;

            return $args;
        } catch (BadRequestHttpException $e) {
            throw new Exception("Invalid Params", Exception::INVALID_PARAMS);
        }
    }

    /**
     * Request has to be sent as POST and with Content-type: application/json
     * @throws \yii\web\HttpException
     */
    private function initRequest($id)
    {
        $contentType = Yii::$app->request->getContentType();
        if (!empty($id) || !Yii::$app->request->getIsPost() || empty($contentType) || $contentType != "application/json")
            throw new HttpException(404, "Page not found");
    }

    /**
     * Try to decode input json data and validate for required fields for JSON-RPC 2.0
     * @throws Exception
     */
    private function parseAndValidateRequestObject()
    {
        $input = file_get_contents('php://input');
        try {
            $requestObject = Json::decode($input, true);
        } catch (InvalidParamException $e) {
            throw new Exception("Parse error", Exception::PARSE_ERROR);
        }

        if (!isset($requestObject['jsonrpc']) || $requestObject['jsonrpc'] !== '2.0' || empty($requestObject['method']))
            throw new Exception("Invalid Request", Exception::INVALID_REQUEST);

        $this->requestObject = $requestObject;
    }

    /**
     * Make associative array where keys are parameters names and values are parameters values
     * @param \yii\base\Action $action
     */
    private function prepareActionParams($action)
    {
        $method = $this->getMethodFromAction($action);
        $methodParams = [];

        foreach ($method->getParameters() as $param) {
            $methodParams[$param->getName()] = $param->getName();
        }

        if (count(array_intersect_key($methodParams, $this->requestObject['params'])) === 0) {
            $additionalParamsNumber = count($methodParams)-count($this->requestObject['params']);
            $this->requestObject['params'] = array_combine(
                $methodParams,
                $additionalParamsNumber ? array_merge($this->requestObject['params'], array_fill(0, $additionalParamsNumber, null)) : $this->requestObject['params']
            );
        }
    }

    /**
     * @param $action
     * @throws Exception
     */
    private function validateActionParams($action)
    {
        $method = $this->getMethodFromAction($action);
        $paramsTypes = $this->getMethodParamsTypes($method);

        foreach ($this->requestObject['params'] as $name=>$value) {
            if (!isset($paramsTypes[$name])) continue;
            $type = $paramsTypes[$name];

            $this->requestObject['params'][$name] = $this->bringValueToType($type, $value);
        }
    }

    /**
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
     * @param $type
     * @param $value
     * @return mixed
     * @throws Exception
     */
    private function bringValueToType($type, $value)
    {
        $typeParts = explode("[]", $type);
        $type = current($typeParts);
        if (count($typeParts) > 2)
            throw new Exception("Type '$type' is invalid", Exception::INTERNAL_ERROR);

        if (count($typeParts) === 2) {
            if (!is_array($value))
                throw new Exception("Invalid Params", Exception::INVALID_PARAMS);

            foreach ($value as $key=>$childValue) {
                $value[$key] = $this->bringValueToType($type, $childValue);
            }
            return $value;
        }

        if (class_exists($type)) {
            if (!is_subclass_of($type, '\\JsonRpc2\\Dto'))
                throw new Exception("Class '$type' MUST be instance of '\\JsonRpc2\\Dto'", Exception::INTERNAL_ERROR);
            return new $type($value);
        } else {
            switch ($type) {
                case "string":
                    return (string)$value;
                    break;
                case "int":
                    return (int)$value;
                    break;
                case "float":
                    return (float)$value;
                    break;
                case "array":
                    throw new Exception("Parameter type 'array' is deprecated. Use square brackets with simply types or DTO based classes instead.", Exception::INTERNAL_ERROR);
                case "bool":
                    return (bool)$value;
                    break;
            }
        }
    }

    /**
     * @param $method \ReflectionMethod
     * @return array
     */
    private function getMethodParamsTypes($method)
    {
        $variable = '\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)';
        preg_match_all("/@param $variable ([\w\\\\\[\]]+)/", $method->getDocComment(), $matches);
        $paramsTypes = array_combine($matches[1], $matches[2]);
        return $paramsTypes;
    }
}