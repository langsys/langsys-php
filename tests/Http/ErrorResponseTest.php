<?php

namespace Langsys\SDK\Tests\Http;

use Langsys\SDK\Config;
use Langsys\SDK\Exception\ApiException;
use Langsys\SDK\Exception\AuthenticationException;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Exception\ValidationException;
use Langsys\SDK\Http\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Error responses as the API sends them, through the real response handler.
 *
 * The API reports an error as an object — {"message", "code", "template"} — not a string.
 * Handing that object to Exception's constructor threw a TypeError, so every API error
 * (an invalid or revoked key included) escaped as a fatal instead of a LangsysException,
 * and anything that degrades to source text on LangsysException never got the chance.
 */
class ErrorResponseTest extends TestCase
{
    /** Exposes the protected handler; nothing else about the client is exercised. */
    private function handle(array $body, int $status)
    {
        $client = new class (new Config(['api_key' => 'k', 'project_id' => 'p'])) extends HttpClient {
            public function respond(string $response, int $status)
            {
                return $this->handleResponse($response, $status);
            }
        };

        return $client->respond(json_encode($body), $status);
    }

    private function caught(array $body, int $status): LangsysException
    {
        try {
            $this->handle($body, $status);
        } catch (LangsysException $e) {
            return $e;
        }
        $this->fail('Expected a LangsysException');
    }

    private const INVALID_KEY = [
        'status' => false,
        'error' => ['message' => 'Invalid API key', 'code' => 'api_key_invalid', 'template' => 'Invalid API key'],
    ];

    public function testStructured401IsAnAuthenticationExceptionWithAStringMessage()
    {
        $e = $this->caught(self::INVALID_KEY, 401);

        $this->assertInstanceOf(AuthenticationException::class, $e);
        $this->assertSame('Invalid API key', $e->getMessage());
        $this->assertSame('api_key_invalid', $e->getErrorCode());
        $this->assertSame(self::INVALID_KEY, $e->getResponseData());
    }

    public function testStructured403IsAnApiExceptionWithAStringMessage()
    {
        // What the live API answers for an unknown key today.
        $e = $this->caught(self::INVALID_KEY, 403);

        $this->assertInstanceOf(ApiException::class, $e);
        $this->assertSame('Invalid API key', $e->getMessage());
        $this->assertSame(403, $e->getHttpStatusCode());
        $this->assertSame('api_key_invalid', $e->getErrorCode());
    }

    public function testStructured422KeepsTheFieldErrors()
    {
        $errors = ['locale' => ['The selected locale is invalid.']];
        $e = $this->caught([
            'status' => false,
            'error' => ['message' => 'The given data was invalid.', 'code' => 'validation_failed'],
            'errors' => $errors,
        ], 422);

        $this->assertInstanceOf(ValidationException::class, $e);
        $this->assertSame('The given data was invalid.', $e->getMessage());
        $this->assertSame($errors, $e->getErrors());
    }

    public function testLegacyStringErrorStillWorks()
    {
        $e = $this->caught(['error' => 'Unauthorized key'], 401);

        $this->assertSame('Unauthorized key', $e->getMessage());
        $this->assertNull($e->getErrorCode());
    }

    public function testAListOfMessagesIsJoined()
    {
        $e = $this->caught(['error' => ['Key revoked', 'Contact the project owner']], 500);

        $this->assertSame('Key revoked; Contact the project owner', $e->getMessage());
    }

    public function testAnObjectWithoutAMessageFallsBackToTheDefault()
    {
        $e = $this->caught(['error' => ['code' => 'rate_limited']], 429);

        $this->assertSame('API error', $e->getMessage());
        $this->assertSame('rate_limited', $e->getErrorCode());
    }

    public function testNoErrorFieldFallsBackToTheDefault()
    {
        $this->assertSame('Unauthorized', $this->caught(['status' => false], 401)->getMessage());
        $this->assertSame('Validation failed', $this->caught(['status' => false], 422)->getMessage());
    }

    public function testASuccessfulResponseIsReturned()
    {
        $this->assertSame(['status' => true, 'data' => []], $this->handle(['status' => true, 'data' => []], 200));
    }
}
