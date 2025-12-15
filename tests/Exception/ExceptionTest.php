<?php

namespace Langsys\SDK\Tests\Exception;

use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Exception\ApiException;
use Langsys\SDK\Exception\AuthenticationException;
use Langsys\SDK\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for exception classes.
 */
class ExceptionTest extends TestCase
{
    public function testLangsysException()
    {
        $responseData = ['error' => 'Something went wrong', 'code' => 'ERR001'];
        $exception = new LangsysException('Test error', 500, null, $responseData);

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertEquals($responseData, $exception->getResponseData());
    }

    public function testLangsysExceptionWithoutResponseData()
    {
        $exception = new LangsysException('Simple error');

        $this->assertEquals('Simple error', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getResponseData());
    }

    public function testApiException()
    {
        $responseData = ['error' => 'Not found'];
        $exception = new ApiException('Resource not found', 404, $responseData);

        $this->assertEquals('Resource not found', $exception->getMessage());
        $this->assertEquals(404, $exception->getHttpStatusCode());
        $this->assertEquals($responseData, $exception->getResponseData());
        $this->assertInstanceOf(LangsysException::class, $exception);
    }

    public function testAuthenticationException()
    {
        $responseData = ['error' => 'Invalid API key'];
        $exception = new AuthenticationException('Unauthorized', $responseData);

        $this->assertEquals('Unauthorized', $exception->getMessage());
        $this->assertEquals(401, $exception->getCode());
        $this->assertEquals($responseData, $exception->getResponseData());
        $this->assertInstanceOf(LangsysException::class, $exception);
    }

    public function testValidationException()
    {
        $errors = [
            'phrase' => ['The phrase field is required.'],
            'category' => ['The category must be a string.'],
        ];
        $responseData = ['error' => 'Validation failed', 'errors' => $errors];
        $exception = new ValidationException('Validation error', $errors, $responseData);

        $this->assertEquals('Validation error', $exception->getMessage());
        $this->assertEquals(422, $exception->getCode());
        $this->assertEquals($errors, $exception->getErrors());
        $this->assertEquals($responseData, $exception->getResponseData());
        $this->assertInstanceOf(LangsysException::class, $exception);
    }

    public function testValidationExceptionWithEmptyErrors()
    {
        $exception = new ValidationException('Validation failed');

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals([], $exception->getErrors());
    }

    public function testExceptionChaining()
    {
        $previous = new \Exception('Original error');
        $exception = new LangsysException('Wrapped error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
