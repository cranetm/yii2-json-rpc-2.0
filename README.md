JSON RPC 2.0 for Yii2
=================
Easiest way to use in 4 steps:
1) Install via composer
   in ./composer.json add into 'require' section
~~~php
    "cranetm/yii2-json-rpc-2.0": "dev-master"
~~~
   and in console/terminal run
~~~php
composer update
~~~

2) Use namespace in your controller
~~~php
use \JsonRpc2\Controller;
~~~
   OR  change extends class to
~~~php
class ServicesController extends \JsonRpc2\Controller
{
    //BODY
}
~~~

3) Create actions in Yii-style like
~~~php
public function actionUpdate($message)
{
	return ["message" => $message];
}
~~~

4) Make json request to controller (used pretty urls without index.php)
~~~php
http://yoursite/services
~~~
   with data
~~~php
{
    "jsonrpc": "2.0",
	"id": 1,
	"method": "update",
	"params": ["hello world"]
}
~~~
   and response will be
~~~php
{"jsonrpc":"2.0","id":1,"result":{"message":"hello world"}}
~~~