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
     * The error's code, when the response carried the langsys error envelope
     * (`error.code`). Branch on this, never on the message text (MSG-2).
     *
     * @return string|null
     */
    public function getErrorCode()
    {
        $data = $this->responseData;

        if (is_array($data) && isset($data['error']) && is_array($data['error']) && isset($data['error']['code']) && is_string($data['error']['code'])) {
            return $data['error']['code'];
        }

        return null;
    }

    /**
     * The server message entries the response carried, wherever they sat (MSG-1).
     *
     * @return \Langsys\SDK\Messages\MessageSet
     */
    public function getServerMessages()
    {
        return \Langsys\SDK\Messages\MessageSet::fromException($this);
    }
}
