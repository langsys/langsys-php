<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Config;
use Langsys\SDK\Exception\ApiException;
use Langsys\SDK\Exception\AuthenticationException;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Exception\ValidationException;
use Langsys\SDK\Http\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * MSG-1 on the SDK's own API: the langsys error envelope carries `error` as an
 * object. The client read `error` as a string, so the new shape handed an array
 * to the exception message.
 */
class ApiErrorEnvelopeTest extends TestCase
{
    /**
     * @return LangsysException
     */
    private function raised($body, $status)
    {
        $client = new HttpClient(new Config(['api_key' => 'test-api-key', 'project_id' => 'project-id']));
        $method = new \ReflectionMethod($client, 'handleResponse');
        $method->setAccessible(true);

        try {
            $method->invoke($client, json_encode($body), $status);
        } catch (LangsysException $e) {
            return $e;
        }

        $this->fail("a $status must raise");
    }

    public function testAValidationFailureRaisesWithItsEntries(): void
    {
        $e = $this->raised([
            'status' => false,
            'error' => [
                'message' => 'The request failed validation.',
                'code' => 'validation_failed',
                'template' => 'The request failed validation.',
                'errors' => [
                    ['field' => 'password', 'code' => 'too_short', 'message' => 'The password must be at least 12 characters.', 'template' => 'The password must be at least {min} characters.', 'params' => ['min' => 12]],
                    ['field' => 'password', 'code' => 'mismatch', 'message' => 'The password confirmation does not match.', 'template' => 'The password confirmation does not match.'],
                ],
            ],
        ], 422);

        $this->assertInstanceOf(ValidationException::class, $e);
        $this->assertSame('The request failed validation.', $e->getMessage());
        $this->assertSame('validation_failed', $e->getErrorCode());
        $this->assertCount(2, $e->getServerMessages()->forField('password'));
    }

    public function testAnErrorObjectsMessageBecomesTheExceptionMessage(): void
    {
        $e = $this->raised([
            'status' => false,
            'error' => ['message' => "Your plan's project limit is 3.", 'code' => 'project_limit_reached', 'template' => "Your plan's project limit is {limit}.", 'params' => ['limit' => 3]],
        ], 402);

        $this->assertInstanceOf(ApiException::class, $e);
        $this->assertSame("Your plan's project limit is 3.", $e->getMessage());
        $this->assertSame(402, $e->getCode());
        $this->assertSame('project_limit_reached', $e->getErrorCode());
        $this->assertCount(1, $e->getServerMessages());
    }

    public function testAnUnauthenticatedEnvelopeReadsTheSameWay(): void
    {
        $e = $this->raised(['status' => false, 'error' => ['message' => 'Unauthenticated.', 'code' => 'unauthenticated', 'template' => 'Unauthenticated.']], 401);

        $this->assertInstanceOf(AuthenticationException::class, $e);
        $this->assertSame('Unauthenticated.', $e->getMessage());
        $this->assertSame('unauthenticated', $e->getErrorCode());
    }

    public function testAStringErrorStillReadsAsTheMessage(): void
    {
        $e = $this->raised(['error' => 'Something broke'], 500);

        $this->assertSame('Something broke', $e->getMessage());
        $this->assertNull($e->getErrorCode());
        $this->assertCount(0, $e->getServerMessages());
    }
}
