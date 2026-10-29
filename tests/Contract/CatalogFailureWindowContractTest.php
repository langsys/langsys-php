<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;

/**
 * CACHE-2 against the contract fixture: a failed catalog fetch is remembered
 * for a bounded window - 3s, doubling on each consecutive failure - and inside
 * it lookups render source without fetching again. The double holds a catalog
 * behind the failing responses, so a lookup that rendered source after a
 * refetch would have rendered the translation instead.
 */
class CatalogFailureWindowContractTest extends ContractTestCase
{
    protected static $seed = 'catalog-failure-window';

    private function clockedClient(World $world)
    {
        $client = new class ($world->key('write'), $world->project(), ['api_url' => self::$baseUrl, 'cache' => new NullCache()]) extends Client {
            public $now = 1000.0;

            protected function currentTime()
            {
                return $this->now;
            }
        };
        $client->setLocale('es-es');

        return $client;
    }

    private function withFailures($times)
    {
        return $this->world('hello', ['faults' => $times > 0 ? [['method' => 'GET', 'path' => '/translations', 'status' => 500, 'times' => $times]] : []]);
    }

    public function testInsideTheWindowALookupRendersSourceWithoutFetching(): void
    {
        $client = $this->clockedClient($this->withFailures(1));

        $this->assertSame('Hello', $client->translate('Hello'), 'the failed fetch degrades to source');

        // A later request on the same long-lived Client, inside the window.
        $client->resetRequestState();
        $client->setLocale('es-es');
        $client->now += 2.5;
        $this->assertSame('Hello', $client->translate('Hello'), 'a refetch would have received the catalog');
        $this->assertFalse($client->hasPendingRegistrations(), 'nothing is queued inside the window');

        $client->now += 0.5;
        $this->assertSame('Hola', $client->translate('Hello'), 'after the window the catalog is fetched');
    }

    public function testTheWindowDoublesOnAConsecutiveFailure(): void
    {
        $client = $this->clockedClient($this->withFailures(2));

        $this->assertSame('Hello', $client->translate('Hello'));
        $client->now += 3;
        $this->assertSame('Hello', $client->translate('Hello'), 'the second fetch fails too');

        $client->now += 5.5;
        $this->assertSame('Hello', $client->translate('Hello'), 'inside the doubled 6s window');

        $client->now += 0.5;
        $this->assertSame('Hola', $client->translate('Hello'));
    }

    public function testControlASuccessfulFirstFetchRendersAtOnce(): void
    {
        $this->assertSame('Hola', $this->clockedClient($this->withFailures(0))->translate('Hello'));
    }
}
