<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Client;

/**
 * WIRE-4: a client built without an API key leaves nothing behind, asserted on
 * what the API holds and on what a configured client sharing its cache then
 * reads.
 *
 * The API would refuse a keyless request, so what it holds is no evidence that
 * nothing was sent (CONF-2); HttpClientTest::testAClientWithoutCredentialsSendsNothing
 * asserts that on a stand-in that records arrivals. What this proves is the
 * residue: the unconfigured client writes no catalog into a cache another
 * client reads, and the configured client then translates and registers as
 * it would on a host where nothing had run.
 */
class UnconfiguredContractTest extends ContractTestCase
{
    protected static $seed = 'unconfigured';

    /**
     * @var string
     */
    private $dir;

    protected function tearDown(): void
    {
        if ($this->dir !== null) {
            foreach (glob($this->dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->dir);
        }
    }

    public function testAnUnconfiguredClientRendersSourceAndLeavesNothingForAConfiguredOne(): void
    {
        $world = $this->world('hello');

        $this->dir = sys_get_temp_dir() . '/langsys-unconfigured-contract-' . bin2hex(random_bytes(4));
        $cache = new FileCache($this->dir);

        $unconfigured = new Client('', $world->project(), [
            'api_url' => self::$baseUrl,
            'cache' => $cache,
            'warn_runtime_requirements' => false,
            'runtime_registration' => true,
        ]);
        $unconfigured->setLocale('es-es');

        $this->assertSame('Hello, Sarah', $unconfigured->translate('Hello, {name}', null, null, null, ['name' => 'Sarah']));
        $this->assertSame('Hello', $unconfigured->translate('Hello'));
        $this->assertSame('Unknown', $unconfigured->translate('Unknown'));
        $this->assertStringContainsString('<p>One</p>', $unconfigured->translateContentBlock('<div><p>One</p><p>Two</p></div>', 'UI'));
        $unconfigured->flushPendingRegistrations();

        $held = $this->held($world->key('write'), $world->project(), 'en-us');
        $this->assertSame(['__uncategorized__' => ['Hello']], $held['phrases'], 'the unconfigured client stored nothing');
        $this->assertSame([], $held['blocks']);

        // Control, on the same cache: a configured client reads the live
        // catalog, not anything the unconfigured one left, and registers what
        // it misses.
        $configured = $this->client($world->key('write'), $world->project(), $cache);
        $configured->setLocale('es-es');

        $this->assertSame('Hola', $configured->translate('Hello'));
        $this->assertSame('Unknown', $configured->translate('Unknown'));
        $this->assertTrue($configured->flushPendingRegistrations()['success']);

        $this->assertSame(['__uncategorized__' => ['Hello', 'Unknown']], $this->held($world->key('write'), $world->project(), 'en-us')['phrases']);
    }
}
