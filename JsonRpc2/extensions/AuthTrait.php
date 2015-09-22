<?php

namespace JsonRpc2\extensions;

/**
 * Provides access to the JSON-RPC 2.0 request object's auth member
 * as described by the JSON RPC v2.0 Authentication Extension.
 * 
 * @see https://jsonrpcx.org/AuthX/HomePage
 * 
 * @author Tyler Ham <tyler@thamtech.com>
 */
trait AuthTrait
{
    
    /**
     * Gets the auth credentials passed in the JSON-RPC 2.0 request object.
     * 
     * @return mixed the auth credentials or null if the auth member was not provided.
     */
    public function getAuthCredentials() {
        return isset($this->requestObject->auth) ? $this->requestObject->auth : null;
    }
}