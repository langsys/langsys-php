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

    public function uses(): array
    {
        return [
            'a phrase' => [function (Client $client) {
                $client->translate('A phrase the catalog lacks', null, 'UI');
            }],
            'a page' => [function (Client $client) {
                $client->translatePage('<html><head><title>T</title></head><body><p>A phrase the catalog lacks</p></body></html>', 'UI');
            }],
            'a content block with a phrase host' => [function (Client $client) {
                $client->translateContentBlock('<div><p data-langsys-phrase>Hi <b>there</b> friend</p></div>', 'UI');
            }],
        ];
    }

    /**
     * Freed by reference counting alone: PHP's cycle collector runs only now
     * and then, so a Client left in a cycle would wait for it, its queue with
     * it, and a worker would hold every Client in between.
     *
     * @dataProvider uses
     */
    public function testAClientIsFreedTheMomentItIsLetGoWhateverItTranslated(callable $use): void
    {
        $http = new MockHttpClient();
        $client = $this->client($http);
        $use($client);
        $this->assertNotEmpty(array_merge($client->getPendingPhrases(), $client->getPendingContentBlocks()));

        $collecting = gc_enabled();
        gc_disable();
        try {
            $held = \WeakReference::create($client);
            unset($client);

            $this->assertNull($held->get());
            $this->assertGreaterThan(0, self::posts($http), 'its queue was sent as it went');
        } finally {
            if ($collecting) {
                gc_enable();
            }
        }
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

    /**
     * With the cycle collector off, so nothing a request leaves in a cycle is
     * freed for it.
     *
     * @dataProvider uses
     */
    public function testAWorkerBuildingAClientPerRequestHoldsNoMoreMemoryAsItServes(callable $use): void
    {
        $serve = function () use ($use) {
            $use($this->client());
        };
        $collecting = gc_enabled();
        gc_disable();
        try {
            for ($i = 0; $i < 100; $i++) {
                $serve();
            }
            $before = memory_get_usage();

            for ($i = 0; $i < 1000; $i++) {
                $serve();
            }

            $this->assertLessThan(4096, memory_get_usage() - $before, 'a thousand requests later');
        } finally {
            if ($collecting) {
                gc_enable();
            }
        }
    }
}

class ClientWithItsOwnDestructor extends Client
{
    public function __destruct()
    {
    }
}
