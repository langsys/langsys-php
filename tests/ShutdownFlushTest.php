<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * What a Client queues is sent once its work is done: when the framework
 * lets the Client go - a long-running worker's request ending - or at the
 * end of the process. Nothing holds a Client only to flush it, so a worker
 * building one per request does not keep every one of them in memory.
 */
class ShutdownFlushTest extends TestCase
{
    private function client(?MockHttpClient $http = null, $class = Client::class)
    {
        $http = $http ?: new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us']]);
        $http->setResponse('GET', 'translations', ['data' => ['UI' => []]]);
        $http->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = new $class('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false]);
        $reflection = new \ReflectionClass(Client::class);
        foreach (['http', 'translations', 'translatableItems'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            if ($property === 'http') {
                $prop->setValue($client, $http);
                continue;
            }
            $resource = $prop->getValue($client);
            $inner = (new \ReflectionClass($resource))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($resource, $http);
        }
        $client->setLocale('es-es');

        return $client;
    }

    private static function posts(MockHttpClient $http)
    {
        return count(array_filter($http->getRequests(), function ($request) {
            return $request['method'] === 'POST';
        }));
    }

    public function testAClientTheFrameworkLetsGoIsFreedAndItsQueueSent(): void
    {
        $http = new MockHttpClient();
        $client = $this->client($http);
        $client->translate('A phrase the catalog lacks', null, 'UI');
        $this->assertNotEmpty($client->getPendingPhrases());

        $held = \WeakReference::create($client);
        unset($client);
        gc_collect_cycles();

        $this->assertNull($held->get(), 'nothing keeps the Client only to flush it');
        $this->assertSame(1, self::posts($http), 'its queue was sent as it went');
    }

    private static function endTheProcess()
    {
        $atShutdown = new \ReflectionMethod(Client::class, 'flushAtShutdown');
        $atShutdown->setAccessible(true);
        $atShutdown->invoke(null);
    }

    public function testAClientStillAliveAtTheEndOfTheProcessIsFlushedThen(): void
    {
        $http = new MockHttpClient();
        $client = $this->client($http);
        $client->translate('A phrase the catalog lacks', null, 'UI');

        self::endTheProcess();

        $this->assertSame(1, self::posts($http));
        $this->assertSame([], $client->getPendingPhrases());
    }

    /**
     * A subclass written before Client had a destructor overrides it without
     * calling it. The next Client to take that one's id is still sent at the
     * end.
     */
    public function testAClientMadeAfterASubclassWithItsOwnDestructorIsStillFlushedAtTheEnd(): void
    {
        $http = new MockHttpClient();
        $first = $this->client($http, ClientWithItsOwnDestructor::class);
        $first->translate('A phrase the catalog lacks', null, 'UI');
        $firstId = spl_object_id($first);
        unset($first);

        $client = $this->client($http);
        $this->assertSame($firstId, spl_object_id($client), 'the second Client takes the first one\'s id');
        $client->translate('Another phrase the catalog lacks', null, 'UI');

        self::endTheProcess();

        $this->assertSame(1, self::posts($http));
        $this->assertSame([], $client->getPendingPhrases());
    }

    public function testAWorkerBuildingAClientPerRequestHoldsNoMoreMemoryAsItServes(): void
    {
        $serve = function () {
            $this->client()->translate('A phrase the catalog lacks', null, 'UI');
        };
        for ($i = 0; $i < 100; $i++) {
            $serve();
        }
        gc_collect_cycles();
        $before = memory_get_usage();

        for ($i = 0; $i < 1000; $i++) {
            $serve();
        }
        gc_collect_cycles();

        $this->assertLessThan(4096, memory_get_usage() - $before, 'a thousand requests later');
    }
}

class ClientWithItsOwnDestructor extends Client
{
    public function __destruct()
    {
    }
}
