<?php

namespace JsonRpc2;

/**
 * Adds support in a [[Controller]] for a "light" method protocol that allows
 * clients to encode the method name in the URL instead of in the "method"
 * parameter of the request object.
 * 
 * One advantage of the light method format is that web server access logs will
 * contain the method invoked, because it is in the URL. The standard "heavy"
 * format will not, because the method is inside the POST data.
 * 
 * Without the LightMethodProtocol, a client would post to
 * ```
 * http://yoursite/services
 * ```
 * with data
 * ```javascript
 * {
 *     "jsonrpc": "2.0",
 *     "id": 1,
 *     "method": "update",
 *     "params": ["world"]
 * }
 * ```
 * 
 * However, with the LightMethodProtocol, a client could instead post to
 * ```
 * http://yoursite/services/update
 * ```
 * with data
 * ```javascript
 * {
 *     "jsonrpc": "2.0",
 *     "id": 1,
 *     "params": ["world"]
 * }
 * ```
 * 
 * The method may be specified both in the URL and in the request object
 * provided that they match.  If the method is specified in both places, an
 * error is thrown if they do not match.
 * 
 * @author Tyler Ham <tyler@thamtech.com>
 */
trait LightMethodProtocolTrait
{
    /**
     * @inheritdoc
     */
    protected function initRequest($id)
    {
        // parent will throw an exception if $id is non-empty, but we want to
        // allow a non-empty $id in Light-mode
        parent::initRequest('');
    }
    
    /**
     * @inheritdoc
     */
    protected function parseAndValidateRequestObject($requestObject, $id)
    {
        if ($requestObject !== null)
        {
            // if method is specified both in the URL and in the $id, and
            // they do not match, we throw an error
            if (!empty($requestObject->method) && !empty($id) && $requestObject->method != $id)
            {
                throw new Exception("Invalid Request - method mismatch", Exception::INVALID_REQUEST);
            }
            
            // if the $requestObject method is not specified or isn't a string,
            // use the $id from the URL instead
            if ((empty($requestObject->method) || "string" != gettype($requestObject->method)) && !empty($id))
            {
                $requestObject->method = $id;
            }
        }
        
        parent::parseAndValidateRequestObject($requestObject, '');
    }
}
