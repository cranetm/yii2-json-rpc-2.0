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
        if (!isset($this->requestObject['id']))
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
     * @return Action the newly created action instance. Null if the ID doesn't resolve into any action.
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
     * @param Action $action the action to be bound with parameters.
     * @param array $params the parameters to be bound to the action.
     * @throws Exception if params are invalid
     * @return array the valid parameters that the action can run with.
     */
    public function bindActionParams($action, $params)
    {
        try {
            return parent::bindActionParams($action, $this->requestObject['params']);
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
        if ($action instanceof InlineAction) {
            $method = new \ReflectionMethod($this, $action->actionMethod);
        } else {
            $method = new \ReflectionMethod($action, 'run');
        }
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
}