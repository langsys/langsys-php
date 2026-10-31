<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Cache\CacheInterface;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * Client::sync(): the phrases a caller holds are registered when new, and the
 * catalog the next request reads is the one the API holds now.
 */
class ClientSyncTest extends TestCase
{
    use BuildsMockClient;

    /**
     * A locale in any spelling clears the catalog the next request reads, as
     * every other entry point normalises it.
     *
     * @dataProvider spellingProvider
     */
    public function testASyncClearsTheCachedCatalogWhateverTheLocaleSpelling($spelling): void
    {
        // Keys compared byte for byte, as Redis compares them.
        $cache = new class implements CacheInterface {
            private $entries = [];

            public function get($key)
            {
                return isset($this->entries[$key]) ? $this->entries[$key] : null;
            }

            public function set($key, $value, $ttl = 3600)
            {
                $this->entries[$key] = $value;

                return true;
            }

            public function has($key)
            {
                return isset($this->entries[$key]);
            }

            public function delete($key)
            {
                unset($this->entries[$key]);

                return true;
            }

            public function clear()
            {
                $this->entries = [];

                return true;
            }
        };
        // An earlier request cached the catalog as it was then.
        $this->mockClient(['UI' => ['Old' => 'Viejo (antes)']], ['cache' => $cache])->getTranslations('es-es');

        $client = $this->mockClient(['UI' => ['Old' => 'Viejo']], ['cache' => $cache]);
        $this->assertTrue($client->sync([['phrase' => 'New', 'category' => 'UI']], $spelling)['synced']);

        $next = $this->mockClient(['UI' => ['Old' => 'Viejo']], ['cache' => $cache]);
        $this->assertSame(['UI' => ['Old' => 'Viejo']], $next->getTranslations('es-es'), 'the next request reads the catalog as it is now');
    }

    public function spellingProvider(): array
    {
        return ['canonical' => ['es-es'], 'region in capitals' => ['es-ES'], 'underscore' => ['es_ES']];
    }
}
