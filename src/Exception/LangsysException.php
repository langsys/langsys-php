<?php

namespace Langsys\SDK\Exception;

use Exception;

/**
 * Base exception for all Langsys SDK exceptions.
 */
class LangsysException extends Exception
{
    /**
     * @var array|null
     */
    protected $responseData;

    /**
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     * @param array|null $responseData
     */
    public function __construct($message = '', $code = 0, $previous = null, $responseData = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseData = $responseData;
    }

    /**
     * Get the response data from the API.
     *
     * @return array|null
     */
    public function getResponseData()
    {
        return $this->responseData;
    }

    /**
     * The API's machine-readable error code, e.g. "api_key_invalid".
     *
     * Present when the API reports the error as an object; null for responses that
     * carry only a message.
     *
     * @return string|null
     */
    public function getErrorCode()
    {
        $error = is_array($this->responseData) && isset($this->responseData['error'])
            ? $this->responseData['error']
            : null;

        return is_array($error) && isset($error['code']) && is_string($error['code']) ? $error['code'] : null;
    }
}
