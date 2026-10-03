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
     * The failed rules, as the API's error envelope lists them: one entry per
     * failed rule, each with its `field`, `code`, `message` and `template`,
     * and `params` where the template has values.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
