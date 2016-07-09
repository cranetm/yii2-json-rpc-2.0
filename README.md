##[JSON-RPC 2.0](http://www.jsonrpc.org/specification) for Yii2 with strict type validation of request and response data

## Table of Contents
 - [Validation features](#validation-features)
 - [Using](#using)
 - [Authentication Extension](#authentication-extension)
 - [Params validation](#params-validation)
  - [Example 1](#example-1)
  - [Example 2](#example-2)
  - [Example 3](#example-3)
  - [Example 4](#example-4)
 - [Response data validation](#response-data-validation)
  - [Example 5](#example-5)
 - [Null values and @null tags](#null-values-and-null-tags)
 - [Value restrictions and @inArray tag](#value-restrictions-and-inarray-tag)
 - [CORS Support](#cors-support)

## Validation features:

1. Validation for required params if its do not have a default value
2. Validation for params types<br/>
    2.1 Using DTOs as structured type<br/>
    2.2 Using square brackets for array types like string[], int[], bool[] or for DTO: ClassName[]<br/>
3. @null tag to allowing null values (by default all data brings to specific type)
4. @inArray tag to restrict values like @inArray["red","brown","yellow"]. Works only with string and int datatypes.


## Using
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
    >Request method MUST be POST and Content-type MUST be application/json

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

## Authentication Extension
If you would like to use the [JSON RPC v2.0 Authentication Extension](https://jsonrpcx.org/AuthX/HomePage),
you may use the \JsonRpc2\extensions\AuthTrait in your instance of
\JsonRpc2\Controller like

~~~php
class ServicesController extends \JsonRpc2\Controller
{
    use \JsonRpc2\extensions\AuthTrait;
}
~~~

Then, you can make a request with an auth token like

~~~javascript
{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "whoami",
    "auth": "some_access_token"
}
~~~

Finally, in a [Filter](http://www.yiiframework.com/doc-2.0/guide-structure-filters.html)
or in your action method, you can call the getAuthCredentials() to get the value
of the "auth" member of the request object like

~~~php
public function actionWhoami($message)
{
    $user = User::findIdentityByAccessToken($this->getAuthCredentials());
    if (!$user) {
        throw new \JsonRpc2\extensions\AuthException('Missing auth',
            \JsonRpc2\extensions\AuthException::MISSING_AUTH);
    }

    return ['uid' => $user->id];
}
~~~

The nature of the "auth" value and how your User identity class will use it to
findIdentityByAccessToken is application specific. For example, in simple
scenarios when each user can only have one access token, you may store the
access token in an access_token column in the user table. See the Yii
[REST Authentication](http://www.yiiframework.com/doc-2.0/guide-rest-authentication.html)
documentation for related information.

<br/>

## Params validation
For validation params data you MUST create [phpDoc @param](http://manual.phpdoc.org/HTMLSmartyConverter/PHP/phpDocumentor/tutorial_tags.param.pkg.html) tags comments with type to action method.<br/>
After that param data will be converted to documented type.

### Example 1
(parsing params from array OR from object and validate them )
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

### Example 2
(simple types like string, int, float, bool)
Let's validate **$message** as int value in our **actionUpdate** and increase it:
~~~php
/**
 * @param int $message
 * @return array
 */
public function actionUpdate($message)
{
    return ["message" => ++$message];
}
~~~

For the next requests:
~~~javascript
{"jsonrpc": "2.0","id": 1,"method": "update","params": [0.1]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": ["world"]}
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
{"jsonrpc": "2.0","id": 1,"method": "update","params": [true]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": [["hello", "world"]]}
{"jsonrpc": "2.0","id": 1,"method": "update","params": [{"hello": "world"}]}
~~~
response will be
~~~javascript
{"jsonrpc":"2.0","id":1,"result":{"message":2}}  //because all previous data converts as 1
~~~

### Example 3
(structured types as [Data transfer object (DTO)](http://en.wikipedia.org/wiki/Data_transfer_object))
In case if params count in method is too long, you can pass them all into one object.<br/>
This object SHOULD contains only data so DTO pattern is used.<br/>
DTO is a class with public variables with described types as **$message** in **actionUpdate**.
> All DTO classes MUST be inherited from \JsonRpc2\Dto, otherwise \JsonRpc2\Exception::INTERNAL_ERROR will be thrown.

> DTO variables MUST have a [phpDoc @var](http://manual.phpdoc.org/HTMLSmartyConverter/PHP/phpDocumentor/tutorial_tags.var.pkg.html) tag comment with type.
> Variable will be converted to this type as well as method's param in **Example 2**

> DTO variable's type can be another DTO class

Let's make a Test DTO with one string variable **$upper**
~~~php
use \JsonRpc2\Dto;

class Test extends Dto {
    /** @var string */
    public $upper;
}
~~~
...and change **actionUpdate** for using Test DTO
~~~php
/**
 * @param \JsonRpc2\Dto\Test $test
 * @return array
 */
public function actionUpdate($test)
{
    return ["message" => strtoupper($test->upper)];
}
~~~
And from now **update** action takes **$test** parameter which converts into Test object.
So, input data has to be object like:
~~~javascript
{"jsonrpc": "2.0","id": 1,"method": "update","params": [{"upper": "hello world"}]}
//or
{"jsonrpc": "2.0","id": 1,"method": "update","params": {"test": {"upper": "hello world"}}}
~~~
and *{"upper": "hello world"}* will be converted to **\JsonRpc2\Test** object with variable validation.
So, response will be:
~~~javascript
{"jsonrpc":"2.0","id":1,"result":{"message":"HELLO WORLD"}}
~~~

### Example 4
(array type)
For better validation 'array' is deprecated as a variable OR parameter type and you MUST use square brackets with one of simply types or DTOs.<br/>
You can use this arrays in actions OR in DTOs and all params data will be validated recursively.

**'Update'** Action:
~~~php
/**
 * @param \JsonRpc2\Dto\Test[] $tests
 * @param string[] $messages
 * @return array
 */
public function actionUpdate($tests, $messages)
{
    //BODY
}
~~~
**Combined** DTO:
~~~php
use \JsonRpc2\Dto;

class Combined extends Dto {
    /** @var string[] */
    public $messages;

    /** @var \JsonRpc2\Dto\Test[] */
    public $tests;
}
~~~

## Response data validation
To reduce unnecessary functionality to bring to the type of data that come from the server, you must validate the data on the server side.<br/>
To do this, you MUST add [@return](http://manual.phpdoc.org/HTMLSmartyConverter/PHP/phpDocumentor/tutorial_tags.return.pkg.html) tag with data type in a phpDoc comment.<br/>
Then the data will be brought to a given type.<br/>
It works the same as **@param** OR **@var** validation.<br/>

DTO User is used in next examples
~~~php
use JsonRpc2\Dto;

class User extends Dto
{
    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var string */
    public $type = 'user';

    /** @var string */
    public $rights;
}
~~~

### Example 5
(response validation):
Let's create action get-users, which imitates fetching data from storage and returns array of Users
~~~php
/**
 * @return \JsonRpc2\Dto\User[]
 */
public function actionGetUsers()
{
    return [
        [
            "id" => "1",
            "name" => "Marco Polo",
            "type" => "admin",
        ],
        [
            "id" => "234",
            "name" => "John Doe",
            "rights" => "settings"
        ]
    ];
}
~~~
Every element of array from response will be converted to User DTO:
~~~javascript
//request
{"jsonrpc": "2.0","id": 1,"method": "get-users","params": []}

//response
{"jsonrpc":"2.0","id":1,"result":[{"id":1,"name":"Marco Polo","type":"admin","rights":""},{"id":234,"name":"John Doe","type":"user","rights":"settings"}]}
~~~
> Even if some values is missing in response array, data brings to User type with all variables described in DTO

## Null values and @null tags
By default null types are not allowed and all null values are converted to specific types:
+ string - ""
+ int/float - 0
+ bool - false
+ DTO - empty object (if default value exists, it will use)
+ array - []

But in many cases you need a null value and you need to add tag @null in the next line after the description of the type of data.

Let's update User's rights variable to be nullable
~~~php
    /**
     * @var string
     * @null
     */
    public $rights;
~~~
~~~javascript
//request
{"jsonrpc": "2.0","id": 1,"method": "get-users","params": []}

//response
{"jsonrpc":"2.0","id":1,"result":[{"id":1,"name":"Marco Polo","type":"admin","rights":null},{"id":234,"name":"John Doe","type":"user","rights":"settings"}]}
~~~
As we can see, rights variable for Marco Polo is null now.

## Value restrictions and @inArray tag
There are many cases where the value may be limited to several variants and should be validated for their presence. <br/>
How it works?<br/>
Let's make restrictions for variable User's rights and try to make request.
~~~php
    /**
     * @var string
     * @inArray["dashboard","settings"]
     */
    public $rights;
~~~

~~~javascript
//request
{"jsonrpc": "2.0","id": 1,"method": "get-users","params": []}

//response
{"jsonrpc":"2.0","id":1,"error":{"code":-32602,"message":"string value '' is not allowed. Allowed values is 'dashboard','settings'","data":null},"result":[]}
~~~
Ups... there is error occurs for Marco Polo and about null value in rights which converts to string and became empty string "".<br/>
But there are restrictions with no empty strings (["dashboard","settings"]) so we have an error.<br/>
To prevent this you MUST define allowed default value for $rights OR add tag @null, OR update restrictions (add "" to inArray list), OR you can define allowed rights for Marco Polo.
~~~php
....
   return [
        [
            "id" => "1",
            "name" => "Marco Polo",
            "type" => "admin",
            "rights" => "dashboard",
        ],
...
~~~
And response will be
~~~javascript
{"jsonrpc":"2.0","id":1,"result":[{"id":1,"name":"Marco Polo","type":"admin","rights":"dashboard"},{"id":234,"name":"John Doe","type":"user","rights":"settings"}]}
~~~

## CORS Support
Extention supports CORS requests from 1.2.5 release.
You may use CORS filter by attaching it as a behavior to a controller, just follow instructions [here](http://www.yiiframework.com/doc-2.0/yii-filters-cors.html)

<br/>
<br/>
#####If you have a problem with functionality not be afraid to register it here.

#####Thanks.
