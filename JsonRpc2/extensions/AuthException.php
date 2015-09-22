<?php

namespace JsonRpc2\extensions;

/**
 * An extension of \JsonRpc2\Exception with additional error codes defined
 * by the JSON RPC v2.0 Authentication Extension.
 * 
 * @see https://jsonrpcx.org/AuthX/HomePage
 * 
 * @author Tyler Ham <tyler@thamtech.com>
 */
class AuthException extends \JsonRpc2\Exception
{
    const MISSING_AUTH = -32651;
    const INVALID_AUTH = -32652;
    
    public function __construct($message, $code, $data = null)
    {
        parent::__construct($message, $code, $data);
    }
}