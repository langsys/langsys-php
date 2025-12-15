<?php

namespace Langsys\SDK\Exception;

/**
 * Exception thrown when validation fails (422 Unprocessable Entity).
 */
class ValidationException extends LangsysException
{
    /**
     * @var array
     */
    protected $errors;

    /**
     * @param string $message
     * @param array $errors
     * @param array|null $responseData
     */
    public function __construct($message = 'Validation failed', $errors = [], $responseData = null)
    {
        parent::__construct($message, 422, null, $responseData);
        $this->errors = $errors;
    }

    /**
     * Get validation errors.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
