##JSON-RPC 2.0 for Yii2 with input data validation
Easiest way to use in 4 steps:<br/>

1. Install via composer

    in ./composer.json add into 'require' section
    ~~~javascript
        "cranetm/yii2-json-rpc-2.0": "1.*"
    ~~~
    and in console/terminal run
    ~~~php
    composer update
    ~~~
2. Use namespace in your controller

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
3. Create actions in Yii-style like

    ~~~php
    public function actionUpdate($message)
    {
	    return ["message" => "hello ".$message];
    }
    ~~~
4. Make json request to controller (used pretty urls without index.php)

    ~~~javascript
    http://yoursite/services
    ~~~
   with data
    ~~~javascript
    {
        "jsonrpc": "2.0",
	    "id": 1,
	    "method": "update",
	    "params": ["world"]
    }
    ~~~
    and response will be
    ~~~javascript
    {"jsonrpc":"2.0","id":1,"result":{"message":"hello world"}}
    ~~~

<br/>

###Input data validation
For validation input data you have to create [phpDoc](http://manual.phpdoc.org/) comments to action method where you document a function parameters.
After that input data will be converted to documented type.

#####Example 1 (parsing params from array OR from object and validate them )
In JSON-RPC params for method can received to server as array or as object, where keys are params names and values are params values.
> In example in **Step4** we sent params as array and in this case first element of array is a first method param, second element - second param and etc.

But we can receive params as associative object and in this case param's order is not necessary:
~~~javascript
{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "update",
    "params": {"var1":"val1","var2":"val2","message":"world"}
}
~~~
> All unused in method params, which was received, will be ignored

> If method's param have default value it can be passed in request.
> Instead this param is required and if it will be missing, \JsonRpc2\Exception::INVALID_PARAMS will be thrown

#####Example 2 (simple types like string, int, float, bool)
Let's validate **$message** as int value in our **actionUpdate** and increase it:
~~~php
/**
 * @param $message int
 * @return array
 */
public function actionUpdate($message)
{
    return ["message" => ++$message];
}
~~~

For the next requests:
~~~javascript
{"jsonrpc": "2.0","id": 1,"method": "update","params": ["world"]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": [0.1]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": [false]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": [{}]} //empty object
{"jsonrpc": "2.0","id": 1,"method": "update","params": [[]]} //empty array
~~~
response will be
~~~javascript
{"jsonrpc":"2.0","id":1,"result":{"message":1}} //because all previous data converts as 0
~~~
But for the next requests:
~~~javascript
{"jsonrpc": "2.0","id": 1,"method": "update","params": [1]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": ["1world"]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": [["hello", "world"]]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": [{"hello": "world"}]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": [true]}
~~~
response will be
~~~javascript
{"jsonrpc":"2.0","id":1,"result":{"message":2}}  //because all previous data converts as 1
~~~

#####Example 3 (structured types as [Data transfer object (DTO)](http://en.wikipedia.org/wiki/Data_transfer_object))
In case if params count in method is too long, you can pass them all into one object.<br/>
This object SHOULD contains only data so DTO pattern is used.<br/>
DTO is a class with public variables with described types as **$message** in **actionUpdate**.
> All DTO classes MUST be inherited from \JsonRpc2\Dto, otherwise \JsonRpc2\Exception::INTERNAL_ERROR will be thrown.

> DTO variables MUST have a [@var phpDoc](http://manual.phpdoc.org/HTMLSmartyConverter/PHP/phpDocumentor/tutorial_tags.var.pkg.html) comment with type.
> Variable will be converted to this type as well as method's param in **Example 2**

> DTO variable's type can be another DTO class

Let's make a Test DTO with one string variable **$message**
~~~php
use \JsonRpc2\Dto;

class Test extends Dto {
    /** @var string */
    public $message;
}
~~~
...and change **actionUpdate** for using Test DTO
~~~php
/**
 * @param $test \JsonRpc2\Dto\Test
 * @return array
 */
public function actionUpdate($test)
{
    return ["message" => strtoupper($test->message)];
}
~~~
And from now **update** action takes **$test** parameter which converts into Test object.
So, input data has to be object like:
~~~javascript
{"jsonrpc": "2.0","id": 1,"method": "update","params": [{"message": "hello world"}]}
//or
{"jsonrpc": "2.0","id": 1,"method": "update","params": {"test": {"message": "hello world"}}}
~~~
and *{"message": "hello world"}* will be converted to **\JsonRpc2\Test** object with variable validation.
So, response will be:
~~~javascript
{"jsonrpc":"2.0","id":1,"result":{"message":"HELLO WORLD"}}
~~~

#####Example 4 (array type)
For better validation 'array' is deprecated as a variable OR parameter type and you MUST use square brackets with one of simply types or DTOs.<br/>
You can use this arrays in actions OR in DTOs and all input data will be validated recursively.

**'Update'** Action:
~~~php
/**
 * @param $tests \JsonRpc2\Dto\Test[]
 * @param $messages string[]
 * @return array
 */
public function actionUpdate($tests, $messages)
{
    //BODY
}
~~~
*Combined* DTO:
~~~php
use \JsonRpc2\Dto;

class Combined extends Dto {
    /** @var string[] */
    public $messages;

    /** @var \JsonRpc2\Dto\Test[] */
    public $tests;
}
~~~

<br/>
<br/>
#####If you have a problem with functionality not be afraid to to register it here.

#####Thanks.
