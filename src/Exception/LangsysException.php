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
}
