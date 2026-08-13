<?php

namespace Langsys\SDK\Tests\Http;

use Langsys\SDK\Config;
use Langsys\SDK\Exception\ApiException;
use Langsys\SDK\Exception\AuthenticationException;
use Langsys\SDK\Exception\ValidationException;
use Langsys\SDK\Http\HttpClient;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    /**
     * Invoke the protected response handler directly - it is where the
     * status-vs-body decisions live.
     */
    private function handle($body, $httpCode)
    {
        $client = new HttpClient(new Config([
            'api_key' => 'test-api-key',
            'project_id' => 'test-project-id',
        ]));

        $method = new \ReflectionMethod($client, 'handleResponse');
        $method->setAccessible(true);

        return $method->invoke($client, $body, $httpCode);
    }

    // =========================================================================
    // Empty success responses (204 and friends)
    // =========================================================================

    /**
     * Some endpoints answer 204 with no content-type and a zero-length body.
     * Parsing that unconditionally throws on a SUCCESSFUL response.
     */
    public function testEmptyBodyOnNoContentIsSuccessNotAParseError()
    {
        $this->assertSame([], $this->handle('', 204));
    }

    public function testEmptyBodyOnOkIsSuccessNotAParseError()
    {
        $this->assertSame([], $this->handle('', 200));
    }

    public function testWhitespaceOnlyBodyOnSuccessIsNotAParseError()
    {
        $this->assertSame([], $this->handle("\n", 204));
    }

    /**
     * An empty body on an error status is still an error - it must surface as
     * the right exception type rather than as a parse failure.
     */
    public function testEmptyBodyOnUnauthorizedStillRaisesAuthenticationException()
    {
        $this->expectException(AuthenticationException::class);
        $this->handle('', 401);
    }

    public function testEmptyBodyOnValidationErrorStillRaisesValidationException()
    {
        $this->expectException(ValidationException::class);
        $this->handle('', 422);
    }

    public function testEmptyBodyOnServerErrorStillRaisesApiException()
    {
        $this->expectException(ApiException::class);
        $this->handle('', 500);
    }

    // =========================================================================
    // Genuinely malformed bodies must still fail
    // =========================================================================

    public function testMalformedJsonOnSuccessStillRaisesParseError()
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Failed to parse JSON response');
        $this->handle('{not json', 200);
    }

    public function testWellFormedSuccessIsDecoded()
    {
        $this->assertSame(
            ['status' => true, 'data' => ['a' => 1]],
            $this->handle('{"status":true,"data":{"a":1}}', 200)
        );
    }

    public function testErrorMessageIsTakenFromTheBodyWhenPresent()
    {
        try {
            $this->handle('{"error":"Invalid API key"}', 401);
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame('Invalid API key', $e->getMessage());
        }
    }
}
