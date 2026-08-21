<?php

namespace Langsys\SDK\Tests\Http;

use Langsys\SDK\Config;
use Langsys\SDK\Http\HttpClient;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    /**
     * TRIPWIRE — when this test fails, a fix is required elsewhere.
     *
     * `Client::resolveWriteDecision()` answers a 'read' key `false` straight from
     * the cached key_type, with no per-request call. That is only correct while
     * this SDK sends no write grant: the server's gate is
     * `type-allows-write OR valid-grant`, so a grant can make a read key
     * write-enabled, and a cached key_type could never know.
     *
     * Any grant implementation must send the token as `X-Write-Grant`, so this
     * assertion breaks the moment grant support lands.
     *
     * **When it does: remove the `KEY_TYPE_READ` short-circuit in
     * `Client::resolveWriteDecision()` so read keys resolve per request, like
     * 'ip_write'.** Do not simply update this test.
     *
     * Asserted on the header rather than on a config flag: the header is what
     * the server gates on, so this fires regardless of how grant support is
     * eventually configured, and cannot be bypassed by an implementation that
     * invents a different config shape. Matched case-insensitively, because HTTP
     * header names are case-insensitive and a guard that catches only one casing
     * is a guard someone walks past by accident.
     */
    public function testNoWriteGrantHeaderIsSent()
    {
        $client = new HttpClient(new Config([
            'api_key' => 'test-api-key',
            'project_id' => 'test-project-id',
        ]));

        $method = new \ReflectionMethod($client, 'getHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($client);

        foreach ($headers as $header) {
            $this->assertStringNotContainsStringIgnoringCase(
                'write-grant',
                $header,
                'Grant support has landed - remove the read-key short-circuit in Client::resolveWriteDecision()'
            );
        }
    }

    /**
     * Some endpoints answer 204 with no content-type and a zero-length body.
     * Decoding that unconditionally raises a parse error on a SUCCESSFUL
     * response.
     */
    public function testEmptyBodyOnSuccessIsNotAParseError()
    {
        $this->assertSame([], $this->handle('', 204));
        $this->assertSame([], $this->handle('', 200));
        $this->assertSame([], $this->handle("\n", 204));
    }

    /**
     * An empty body on an error status is still that error, not a parse failure.
     */
    public function testEmptyBodyOnErrorStatusRaisesTheMatchingException()
    {
        $this->expectException(\Langsys\SDK\Exception\AuthenticationException::class);
        $this->handle('', 401);
    }

    public function testEmptyBodyOnValidationErrorRaisesValidationException()
    {
        $this->expectException(\Langsys\SDK\Exception\ValidationException::class);
        $this->handle('', 422);
    }

    public function testMalformedJsonStillRaisesAParseError()
    {
        $this->expectException(\Langsys\SDK\Exception\ApiException::class);
        $this->expectExceptionMessage('Failed to parse JSON response');
        $this->handle('{not json', 200);
    }

    public function testWellFormedSuccessIsDecoded()
    {
        $this->assertSame(['a' => 1], $this->handle('{"a":1}', 200));
    }

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

    public function testAuthenticatesWithTheXAuthorizationHeader()
    {
        $client = new HttpClient(new Config([
            'api_key' => 'test-api-key',
            'project_id' => 'test-project-id',
        ]));

        $method = new \ReflectionMethod($client, 'getHeaders');
        $method->setAccessible(true);

        $this->assertContains('X-Authorization: test-api-key', $method->invoke($client));
    }
}
