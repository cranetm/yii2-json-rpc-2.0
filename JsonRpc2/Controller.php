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

    /** @var array Stores information about param's types and method's return type */
    private $methodInfo = [
        'params' => [],
        'return' => []
    ];

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
            $dirtyResult = parent::runAction($this->requestObject['method']);
            ob_clean();
            $result = $this->validateResult($dirtyResult);
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

        if (!empty($error))
            $response->data['error'] = $error->toArray();

        if (!empty($result) || is_array($result))
            $response->data['result'] = $result;

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

            //code from parent
            if ($action instanceof InlineAction) {
                $method = new \ReflectionMethod($this, $action->actionMethod);
            } else {
                $method = new \ReflectionMethod($action, 'run');
            }

            $this->parseMethodDocComment($method);
            $this->validateActionParams();
            $params = $this->requestObject['params'];

            $args = [];
            $missing = [];
            $actionParams = [];
            $paramsTypes = $this->getMethodParamsTypes();//changes for array types documented as square brackets

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
     * Validates and brings method params to its types
     * @throws Exception
     */
    private function validateActionParams()
    {
        foreach ($this->requestObject['params'] as $name=>$value) {
            if (!isset($this->methodInfo['params'][$name])) continue;
            $paramInfo = $this->methodInfo['params'][$name];

            $this->requestObject['params'][$name] = Helper::bringValueToType(
                $paramInfo['type'],
                $value,
                $paramInfo['isNullable'],
                $paramInfo['restrictions']
            );
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
            $result[$item['name']] = $item['type'];
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
            $result = Helper::bringValueToType(
                $this->methodInfo['return']['type'],
                $result,
                $this->methodInfo['return']['isNullable'],
                $this->methodInfo['return']['restrictions']
            );
        } else if (empty($result))
            $result = $this->defaultResult;

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
            'type' => '',
            'isNullable' => false,
            'restrictions' => [],
        ];

        $this->methodInfo = [
            'params' => [],
            'return' => []
        ];

        $lines = preg_split ('/$\R?^/m', $method->getDocComment());
        for ($i=0; $i<count($lines); $i++) {
            preg_match("/@param $variableRegex ([\w\\\\\[\]]+)/", $lines[$i], $paramMatches);
            if (!empty($paramMatches)) {
                $subject = &$this->methodInfo['params'][$paramMatches[1]];
                $subject = $infoTpl;
                $subject['name'] = $paramMatches[1];
                $subject['type'] = $paramMatches[2];
            } else {
                preg_match("/@return ([\w\\\\\[\]]+)/", $lines[$i], $paramMatches);
                if (!empty($paramMatches)) {
                    $subject = &$this->methodInfo['return'];
                    $subject = $infoTpl;
                    $subject['type'] = $paramMatches[1];
                }
            }
            if (!empty($subject)) {

                //search in two next lines for @null or @inArray tags
                for ($j=0; $j<2; $j++) {
                    preg_match("/@(null|inArray(\[(.*)\]))/", $lines[$i+1], $tagMatches);
                    if (empty($tagMatches)) break;

                    if (strpos($tagMatches[0], "@inArray") === 0 && in_array($subject['type'], ['string', 'int'])) {
                        eval("\$parsedData = {$tagMatches[2]};");
                        if (!is_array($parsedData))
                            throw new Exception("Invalid syntax in @inArray{$tagMatches[2]}", Exception::INTERNAL_ERROR);
                        $subject['restrictions'] = $parsedData;
                    } elseif (strpos($tagMatches[0], "@null") === 0) {
                        $subject['isNullable'] = true;
                    }

                    $i++;
                }
            }
            unset($subject);
        }
    }
}