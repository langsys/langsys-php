<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\FileCache;

/**
 * Registration against the contract fixture: GATE-5, GATE-6, GATE-7, REG-9 and
 * REG-10, each asserted on the state the server accepted.
 */
class RegistrationContractTest extends ContractTestCase
{
    /** @var string[] */
    private $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function sharedCache()
    {
        $dir = sys_get_temp_dir() . '/langsys-contract-' . bin2hex(random_bytes(4));
        $this->dirs[] = $dir;

        return new FileCache($dir);
    }

    /**
     * GATE-5: a refused send records nothing that suppresses the next attempt.
     * Two requests share a cache, as two requests on one host do; the first
     * request's send is refused, and the second request registers.
     */
    public function testARefusedSendIsRetriedByTheNextRequestOnThePagePath(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], [], [], [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]);
        $cache = $this->sharedCache();

        $first = $this->client('k-write', $cache);
        $first->setLocale('es-es');
        $first->translatePage('<html><body><p>Page phrase</p></body></html>');
        $this->assertFalse($first->flushPendingRegistrations()['success']);
        $this->assertSame([], $this->registeredPhrases(), 'the refused send stored nothing');

        $second = $this->client('k-write', $cache);
        $second->setLocale('es-es');
        $second->translatePage('<html><body><p>Page phrase</p></body></html>');
        $second->flushPendingRegistrations();

        $this->assertSame([[null, 'Page phrase']], $this->registeredPhrases());
    }

    public function testARefusedSendIsRetriedByTheNextRequestOnThePhrasePath(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], [], [], [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]);
        $cache = $this->sharedCache();

        $first = $this->client('k-write', $cache);
        $first->setLocale('es-es');
        $first->translate('Loose phrase');
        $first->flushPendingRegistrations();
        $this->assertSame([], $this->registeredPhrases());

        $second = $this->client('k-write', $cache);
        $second->setLocale('es-es');
        $second->translate('Loose phrase');
        $second->flushPendingRegistrations();

        $this->assertSame([[null, 'Loose phrase']], $this->registeredPhrases());
    }

    /**
     * A key that could report if anything reported: an ip_write key off its
     * allow-list, with discovery enabled, renderer egress configured and a site
     * the page is on. The control below proves a hint from it is stored, so an
     * empty hint list means nothing was reported, not that nothing could be.
     */
    private function seedReportingCapable()
    {
        $this->seedProject(
            [
                'k-write' => ['type' => 'write', 'report_discovered_content' => true],
                'k-reader' => ['type' => 'ip_write', 'ip_allowlist' => ['10.1.2.3'], 'report_discovered_content' => true],
            ],
            ['website_url' => 'https://example.com'],
            ['renderer_egress_ips' => ['10.9.9.9']]
        );
    }

    public function testControlAHintFromTheNonWritingKeyWouldBeStored(): void
    {
        $this->seedReportingCapable();

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nX-Authorization: k-reader\r\n",
            'content' => json_encode(['project_id' => self::PROJECT, 'page_url' => 'https://example.com/pricing']),
            'ignore_errors' => true,
        ]]);
        file_get_contents(self::$baseUrl . '/discovery/hint', false, $context);

        $this->assertCount(1, $this->storedHints());
    }

    private function missOnEveryPath($key)
    {
        $client = $this->client($key);
        $client->setLocale('es-es');
        $client->translate('Phrase path');
        $client->translateContentBlock('<div><p>Block one</p><p>Block two</p></div>', 'UI');
        $client->translatePage('<html><body><p>Page path</p></body></html>');
        $client->flushPendingRegistrations();
    }

    /**
     * GATE-7, the writer's direction: every detection path registers.
     */
    public function testAWriterRegistersFromEveryPath(): void
    {
        $this->seedReportingCapable();

        $this->missOnEveryPath('k-write');

        $this->assertEqualsCanonicalizing([[null, 'Phrase path'], [null, 'Page path']], $this->registeredPhrases());
        $this->assertSame([['UI', ['Block one', 'Block two']]], $this->registeredBlocks());
    }

    /**
     * GATE-6: a writer does not report. The double stores no hint from a caller
     * that can write, so the absence is shown after drift: the session learns it
     * may write, then the key loses its allow-list, and a hint from it would now
     * be stored - the control, sent in that world.
     */
    public function testAWriterReportsNothingEvenOnceTheServerWouldStoreAHint(): void
    {
        $keys = function (array $allowList) {
            return ['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => $allowList, 'report_discovered_content' => true]];
        };
        $this->seedProject($keys(['127.0.0.1']), ['website_url' => 'https://example.com'], ['renderer_egress_ips' => ['10.9.9.9']]);
        $client = $this->client('k-ip');
        $client->setLocale('es-es');
        $this->assertTrue($client->canWrite());

        $this->seedProject($keys(['10.1.2.3']), ['website_url' => 'https://example.com'], ['renderer_egress_ips' => ['10.9.9.9']]);
        $client->translate('Phrase path');
        $client->translatePage('<html><body><p>Page path</p></body></html>');
        $client->flushPendingRegistrations();
        $this->assertSame([], $this->storedHints(), 'the writer reported nothing');

        $this->postHint('k-ip', 'https://example.com/pricing');
        $this->assertCount(1, $this->storedHints(), 'control: in the drifted world a hint from this key is stored');
    }

    /**
     * GATE-7, the non-writer's direction: a server SDK's other lane is its log,
     * never a report (HINT-2), so nothing is reported - by a key whose hint the
     * double would store, as the control above shows.
     */
    public function testANonWriterReportsNothingFromAnyPath(): void
    {
        $this->seedReportingCapable();

        $this->missOnEveryPath('k-reader');

        $this->assertSame([], $this->storedHints());
    }

    /**
     * GATE-6 and GATE-7: a non-writer registers nothing from any path. Shown
     * where the double would accept the writes: the session learns it may not
     * write, then the key joins its allow-list.
     */
    public function testANonWriterHoldsBackEveryPathOnceTheServerWouldAcceptIt(): void
    {
        $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['10.1.2.3']]]);
        $client = $this->client('k-ip');
        $client->setLocale('es-es');
        $this->assertFalse($client->canWrite());

        $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['127.0.0.1']]]);
        $client->translate('Phrase path');
        $client->translateContentBlock('<div><p>Block one</p><p>Block two</p></div>', 'UI');
        $client->translatePage('<html><body><p>Page path</p></body></html>');
        $client->flushPendingRegistrations();
        $this->assertSame([], $this->registeredPhrases());
        $this->assertSame([], $this->registeredBlocks());

        $this->missOnEveryPath('k-ip');
        $this->assertNotEmpty($this->registeredPhrases(), 'control: in the drifted world a session that learns it may write does');
    }

    /**
     * REG-9: chunked to the limit the server advertises, on the phrase and the
     * block path. The double answers 422 to an oversized batch, so anything sent
     * unchunked would be missing from the state.
     */
    public function testEveryItemIsAcceptedWhenTheServerAdvertisesASmallBatchLimit(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], [], ['batch_limit' => 3]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');
        for ($i = 1; $i <= 7; $i++) {
            $client->translate("Phrase $i");
            $client->translateContentBlock("<div><p>Block $i a</p><p>Block $i b</p></div>", 'UI');
        }
        $client->flushPendingRegistrations();

        $this->assertCount(7, $this->registeredPhrases());
        $this->assertCount(7, $this->registeredBlocks());
    }

    /**
     * REG-10: no throw into the caller, and the result says what happened - a
     * refused send is not success, an accepted one is.
     */
    public function testAFailedSendReportsFailureAndStoresNothing(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], [], [], [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->translate('Refused');
        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame([], $this->registeredPhrases());
    }

    public function testAnAcceptedSendReportsSuccess(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->translate('Accepted');
        $result = $client->flushPendingRegistrations();

        $this->assertTrue($result['success']);
        $this->assertSame([[null, 'Accepted']], $this->registeredPhrases());
    }

    public function testASkippedWriteIsNotReportedAsSuccess(): void
    {
        $this->seedProject(['k-read' => ['type' => 'read']]);

        $client = $this->client('k-read');
        $client->setLocale('es-es');
        $client->translate('Skipped');
        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame('not_write_enabled', $result['reason']);
        $this->assertSame([], $this->registeredPhrases());
    }

    /**
     * REG-10: while the catalog cannot be read no miss is decided and nothing
     * is queued; the flush reports that skip by name, distinct from a refused
     * send and from a session that may not write.
     */
    public function testAFlushWhileTheCatalogIsUnavailableNamesIt(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], [], [], [['method' => 'GET', 'path' => '/translations', 'status' => 500, 'times' => 5]]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->translate('Undecided');
        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame('catalog_unavailable', $result['reason']);
        $this->assertSame([], $this->registeredPhrases());
    }

    /**
     * Once a later read succeeds the catalog is available again, and a flush
     * with nothing to send is a success.
     */
    public function testARecoveredCatalogIsNoLongerReportedUnavailable(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], ['phrases' => [['phrase' => 'Known', 'translations' => ['es-es' => 'Conocido']]]], [], [['method' => 'GET', 'path' => '/translations', 'status' => 500, 'times' => 1]]);

        $client = $this->clockedClient();

        $this->assertSame('Known', $client->translate('Known'));
        $this->assertSame('catalog_unavailable', $client->flushPendingRegistrations()['reason']);

        $client->now += 3;
        $this->assertSame('Conocido', $client->translate('Known'), 'the catalog is read again');
        $result = $client->flushPendingRegistrations();

        $this->assertTrue($result['success']);
        $this->assertNull($result['reason']);
    }

    /**
     * A catalog another request has since cached is available: reading it
     * from the cache clears the mark as a fetch would.
     */
    public function testACatalogRecoveredThroughTheCacheIsNoLongerReportedUnavailable(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], ['phrases' => [['phrase' => 'Known', 'translations' => ['es-es' => 'Conocido']]]], [], [['method' => 'GET', 'path' => '/translations', 'status' => 500, 'times' => 1]]);
        $cache = $this->sharedCache();

        $client = $this->client('k-write', $cache);
        $client->setLocale('es-es');
        $this->assertSame('Known', $client->translate('Known'));

        $other = $this->client('k-write', $cache);
        $other->setLocale('es-es');
        $this->assertSame('Conocido', $other->translate('Known'), 'another request caches the catalog');

        $this->assertSame('Conocido', $client->translate('Known'), 'read from the cache');
        $this->assertTrue($client->flushPendingRegistrations()['success']);
    }

    /**
     * A later request on a long-lived Client, inside the failed fetch's
     * window, cannot read the catalog either, and reports it; a request that
     * never reads the catalog reports nothing unavailable.
     */
    public function testTheMarkIsPerRequest(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], [], [], [['method' => 'GET', 'path' => '/translations', 'status' => 500, 'times' => 1]]);

        $client = $this->clockedClient();
        $client->translate('Undecided');
        $this->assertSame('catalog_unavailable', $client->flushPendingRegistrations()['reason']);

        $client->resetRequestState();
        $this->assertTrue($client->flushPendingRegistrations()['success'], 'this request read no catalog');

        $client->resetRequestState();
        $client->now += 1;
        $client->translate('Undecided');
        $this->assertSame('catalog_unavailable', $client->flushPendingRegistrations()['reason'], 'inside the window the catalog is still unavailable');
    }

    private function clockedClient()
    {
        $client = new class ('k-write', self::PROJECT, ['api_url' => self::$baseUrl, 'cache' => new \Langsys\SDK\Cache\NullCache()]) extends \Langsys\SDK\Client {
            public $now = 1000.0;

            protected function currentTime()
            {
                return $this->now;
            }
        };
        $client->setLocale('es-es');

        return $client;
    }

    public function testASendTheServerRefusesIsDistinctFromBothSkips(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], [], [], [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->translate('Refused');

        $this->assertSame('send_failed', $client->flushPendingRegistrations()['reason']);
    }

    public function testAWriteEnabledRegistrationSucceedsAndReachesTheNextCatalogRead(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->translate('Arrives');
        $result = $client->flushPendingRegistrations();

        $this->assertTrue($result['success']);
        $this->assertNull($result['reason']);

        $next = $this->client('k-write');
        $this->assertArrayHasKey('Arrives', $next->translations()->getTranslationMap('es-es')['__uncategorized__']);
    }
}
