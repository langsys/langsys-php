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

    // =========================================================================
    // WIRE-5 — an integrator must be able to point the SDK at a double
    // =========================================================================

    /**
     * The row for this rule cited `src/Config.php` and the README. That is a
     * claim that the knob EXISTS, which is not the rule: WIRE-5 is about a
     * request arriving somewhere else. A wrong precedence between the option and
     * the env var, a base URL read but never threaded into cURL, a hard-coded
     * host on one endpoint - every one of those satisfies the old evidence and
     * defeats the rule.
     *
     * So this stands up a real server on a loopback port, points an otherwise
     * untouched Client at it, and asserts on what the server RECEIVED.
     *
     * @dataProvider redirectionMechanismProvider
     */
    public function testRedirectedRequestsActuallyArriveAtTheDouble($mechanism)
    {
        $log = tempnam(sys_get_temp_dir(), 'langsys-wire5-');
        $port = $this->freePort();
        $router = dirname(__DIR__) . '/fixtures/wire5-double.php';

        $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        // Non-blocking, so reading stderr for a diagnostic cannot hang the suite.
        $server = proc_open(
            sprintf('exec php -S 127.0.0.1:%d %s', $port, escapeshellarg($router)),
            $descriptors,
            $pipes,
            null,
            // getenv(), not $_ENV: under the default variables_order the CLI
            // populates neither $_ENV nor $HTTP_ENV_VARS, so passing $_ENV hands
            // the child an environment with no PATH and `php` is never found.
            array_merge(getenv(), ['LANGSYS_WIRE5_LOG' => $log])
        );

        $this->assertIsResource($server, 'could not start the stand-in API');
        stream_set_blocking($pipes[2], false);

        try {
            // Deliberately an assertion, not markTestSkipped(). A test that
            // quietly skips when its subject fails to come up is a test that can
            // never fail, which is the only outcome worse than not having it.
            $this->assertTrue(
                $this->waitForPort($port),
                'the stand-in API never accepted a connection: ' . stream_get_contents($pipes[2])
            );

            $base = 'http://127.0.0.1:' . $port;

            $projectId = 'project-' . $mechanism;

            // An explicit NullCache instance, and the reason is worth recording.
            // `'cache' => 'memory'` is not a thing: initializeCache() only takes
            // a CacheInterface INSTANCE by that key and otherwise falls back to
            // the cache_driver setting, whose default is a FileCache in the
            // system temp directory. So the string silently bought a real
            // ON-DISK cache, shared across runs - the first run passed, cached
            // the authorize response, and every run after it asserted on a cache
            // hit that issued no request at all. A test that passes once and
            // then can never fail again.
            $cache = new \Langsys\SDK\Cache\NullCache();

            // The integration is UNTOUCHED in the env-var case - that is the
            // load-bearing half of the rule. A constructor option requires
            // editing the caller; an env var does not.
            if ($mechanism === 'env') {
                putenv('LANGSYS_API_URL=' . $base);
                $client = new \Langsys\SDK\Client('test-api-key', $projectId, ['cache' => $cache]);
            } else {
                $client = new \Langsys\SDK\Client('test-api-key', $projectId, [
                    'cache' => $cache,
                    'api_url' => $base,
                ]);
            }

            $client->authorize();

            $lines = array_filter(explode("\n", (string) file_get_contents($log)));
            $this->assertNotEmpty($lines, 'no request reached the double - the redirection did not take effect');

            $request = json_decode(reset($lines), true);
            $this->assertSame('GET', $request['method']);
            $this->assertStringContainsString('authorize-project/' . $projectId, $request['uri']);
            $this->assertSame('127.0.0.1:' . $port, $request['host'], 'the Host header must follow the redirect too');
            $this->assertTrue($request['has_auth'], 'credentials must still be sent to the redirected host');
        } finally {
            if ($mechanism === 'env') {
                putenv('LANGSYS_API_URL');
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate($server);
            proc_close($server);
            @unlink($log);
        }
    }

    public function redirectionMechanismProvider()
    {
        return [
            'constructor option' => ['option'],
            'environment variable' => ['env'],
        ];
    }

    private function freePort()
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function waitForPort($port, $timeoutSeconds = 5)
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $connection = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 0.1);

            if ($connection !== false) {
                fclose($connection);
                return true;
            }

            usleep(50000);
        }

        return false;
    }
}
