<?php

namespace Langsys\SDK\Tests\Http;

use Langsys\SDK\Config;
use Langsys\SDK\Http\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the request headers every call sends.
 */
class HttpClientTest extends TestCase
{
    /**
     * @return array
     */
    private function headersFor(array $options)
    {
        $client = new HttpClient(new Config($options));
        $method = new \ReflectionMethod($client, 'getHeaders');
        $method->setAccessible(true);

        return $method->invoke($client);
    }

    /**
     * Without this header the API pre-flattens a machine-promoted plural to its
     * `other` branch, so "{count, plural, one {Tienes # mensaje nuevo} other {…}}"
     * reaches the SDK as "Tienes {count} mensajes nuevos." and a count of 1 renders
     * "Tienes 1 mensajes nuevos.". The Interpolator resolves ICU itself, so the SDK
     * must ask for it, as the JS, Python and Ruby SDKs do.
     */
    public function testAsksForRawIcuMessages()
    {
        $headers = $this->headersFor(['api_key' => 'key', 'project_id' => 'project']);

        $this->assertContains('X-Langsys-Capabilities: icu', $headers);
    }

    public function testAsksForRawIcuMessagesWithoutAnApiKey()
    {
        $headers = $this->headersFor(['project_id' => 'project']);

        $this->assertContains('X-Langsys-Capabilities: icu', $headers);
        $this->assertNotContains('X-Authorization: ', $headers);
    }

    public function testSendsTheApiKey()
    {
        $headers = $this->headersFor(['api_key' => 'secret', 'project_id' => 'project']);

        $this->assertContains('X-Authorization: secret', $headers);
    }
}
