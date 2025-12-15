<?php

namespace Langsys\SDK\Exception;

/**
 * Exception thrown when authentication fails (401 Unauthorized).
 */
class AuthenticationException extends LangsysException
{
    /**
     * @param string $message
     * @param array|null $responseData
     */
    public function __construct($message = 'Unauthorized', $responseData = null)
    {
        parent::__construct($message, 401, null, $responseData);
    }
}
