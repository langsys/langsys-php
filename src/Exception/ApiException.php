<?php

namespace Langsys\SDK\Exception;

/**
 * Exception thrown for general API errors.
 */
class ApiException extends LangsysException
{
    /**
     * @var int
     */
    protected $httpStatusCode;

    /**
     * @param string $message
     * @param int $httpStatusCode
     * @param array|null $responseData
     */
    public function __construct($message = 'API error', $httpStatusCode = 500, $responseData = null)
    {
        parent::__construct($message, $httpStatusCode, null, $responseData);
        $this->httpStatusCode = $httpStatusCode;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }
}
