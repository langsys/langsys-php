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
    private function clockedClient()
    {
        $client = new class ('k-write', self::PROJECT, ['api_url' => self::$baseUrl, 'cache' => new NullCache()]) extends Client {
            public $now = 1000.0;

            protected function currentTime()
            {
                return $this->now;
            }
        };
        $client->setLocale('es-es');

        return $client;
    }

    private function seedWithFailures($times)
    {
        $faults = $times > 0 ? [['method' => 'GET', 'path' => '/translations', 'status' => 500, 'times' => $times]] : [];

        $this->seedProject(
            ['k-write' => ['type' => 'write']],
            ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]],
            [],
            $faults
        );
    }

    public function testInsideTheWindowALookupRendersSourceWithoutFetching(): void
    {
        $this->seedWithFailures(1);
        $client = $this->clockedClient();

        $this->assertSame('Hello', $client->translate('Hello'), 'the failed fetch degrades to source');

        // A later request on the same long-lived Client, inside the window.
        $client->resetRequestState();
        $client->now += 2.5;
        $this->assertSame('Hello', $client->translate('Hello'), 'a refetch would have received the catalog');
        $this->assertFalse($client->hasPendingRegistrations(), 'nothing is queued inside the window');

        $client->now += 0.5;
        $this->assertSame('Hola', $client->translate('Hello'), 'after the window the catalog is fetched');
    }

    public function testTheWindowDoublesOnAConsecutiveFailure(): void
    {
        $this->seedWithFailures(2);
        $client = $this->clockedClient();

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
        $this->seedWithFailures(0);

        $this->assertSame('Hola', $this->clockedClient()->translate('Hello'));
    }
}
