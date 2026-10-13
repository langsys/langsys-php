<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Client;

/**
 * WIRE-4: a client built without an API key leaves nothing behind, asserted on
 * the state the double accepted and on what a configured client sharing its
 * cache then reads.
 *
 * The double would refuse a keyless request, so its state alone is no evidence
 * that nothing was sent (CONF-2); HttpClientTest::testAClientWithoutCredentialsSendsNothing
 * asserts that on a stand-in that records arrivals. What this proves is the
 * residue: the unconfigured client writes no catalog into a cache another
 * client reads, and the configured client then translates and registers as
 * it would on a host where nothing had run.
 */
class UnconfiguredContractTest extends ContractTestCase
{
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
        $this->seedProject(
            ['k-write' => ['type' => 'write']],
            ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]]
        );

        $this->dir = sys_get_temp_dir() . '/langsys-unconfigured-contract-' . bin2hex(random_bytes(4));
        $cache = new FileCache($this->dir);

        $unconfigured = new Client('', self::PROJECT, [
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

        $this->assertSame([[null, 'Hello']], $this->registeredPhrases(), 'the unconfigured client stored nothing');
        $this->assertSame([], $this->registeredBlocks());

        // Control, on the same cache: a configured client reads the live
        // catalog, not anything the unconfigured one left, and registers what
        // it misses.
        $configured = $this->client('k-write', $cache);
        $configured->setLocale('es-es');

        $this->assertSame('Hola', $configured->translate('Hello'));
        $this->assertSame('Unknown', $configured->translate('Unknown'));
        $this->assertTrue($configured->flushPendingRegistrations()['success']);

        $this->assertSame([[null, 'Hello'], [null, 'Unknown']], $this->registeredPhrases());
    }
}
