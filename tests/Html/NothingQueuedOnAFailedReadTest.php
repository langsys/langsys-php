<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Exception\ApiException;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

class CatalogReadFails extends MockHttpClient
{
    public function get($endpoint, array $params = [])
    {
        if (strpos($endpoint, 'translations') === 0) {
            parent::get($endpoint, $params);
            throw new ApiException('Server error', 500);
        }

        return parent::get($endpoint, $params);
    }
}

/**
 * REG-13 on every rendering path: whether a unit is unregistered is decided only
 * against a catalog that loaded. When the read fails nothing is queued - a miss
 * cannot be told from a hit, and registering on a guess re-posts what the server
 * already holds.
 */
class NothingQueuedOnAFailedReadTest extends TestCase
{
    use BuildsMockClient;

    public function testThePagePathQueuesNothing(): void
    {
        $client = $this->mockClient([], [], null, new CatalogReadFails());
        $client->setLocale('es-es');
        $client->translatePage('<html><head><title>Title</title></head><body><p>Hello</p><div><p>One <b>two</b></p></div></body></html>');

        $this->assertFalse($client->hasPendingRegistrations());
        $client->flushPendingRegistrations();
        $this->assertSame([], $this->registered());
    }

    public function testTheBlockPathQueuesNothing(): void
    {
        $client = $this->mockClient([], [], null, new CatalogReadFails());
        $client->setLocale('es-es');
        $client->translateContentBlock('<div><p>One</p><p>Two</p></div>', 'UI');

        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testControlALoadedEmptyCatalogDoesQueue(): void
    {
        $client = $this->mockClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');
        $client->translatePage('<html><body><p>Hello</p></body></html>');

        $this->assertTrue($client->hasPendingRegistrations());
    }
}
