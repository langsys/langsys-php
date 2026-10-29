<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;

/**
 * Registration against the contract fixture: GATE-5, GATE-6, GATE-7, REG-9 and
 * REG-10, each asserted on what the API then holds.
 */
class RegistrationContractTest extends ContractTestCase
{
    protected static $seed = 'registration';

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

    private function writer(World $world, $role, $cache = null)
    {
        $client = $this->client($world->key($role), $world->project(), $cache);
        $client->setLocale('es-es');

        return $client;
    }

    /** The phrases the project holds, by category, as its catalog serves them. */
    private function phrases(World $world, $role)
    {
        return $this->held($world->key($role), $world->project(), 'en-us')['phrases'];
    }

    /** The phrases of each block the project holds, by category. */
    private function blocks(World $world, $role)
    {
        $blocks = [];
        foreach ($this->held($world->key($role), $world->project(), 'en-us')['blocks'] as $category => $held) {
            $blocks[$category] = array_values($held);
        }

        return $blocks;
    }

    private function postHint(World $world, $role)
    {
        $answer = $this->api('/discovery/hint', ['key' => $world->key($role), 'body' => ['project_id' => $world->project(), 'page_url' => 'https://site.local/pricing']]);
        $this->assertSame(204, $answer['status'], 'every hint path answers 204');
    }

    /**
     * GATE-5: a refused send records nothing that suppresses the next attempt.
     * Two requests share a cache, as two requests on one host do; the first
     * request's send is refused, and the second request registers.
     */
    public function testARefusedSendIsRetriedByTheNextRequestOnThePagePath(): void
    {
        $world = $this->world('writer', ['faults' => [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]]);
        $cache = $this->sharedCache();

        $first = $this->writer($world, 'write', $cache);
        $first->translatePage('<html><body><p>Page phrase</p></body></html>');
        $this->assertFalse($first->flushPendingRegistrations()['success']);
        $this->assertSame([], $this->phrases($world, 'write'), 'the refused send stored nothing');

        $second = $this->writer($world, 'write', $cache);
        $second->translatePage('<html><body><p>Page phrase</p></body></html>');
        $second->flushPendingRegistrations();

        $this->assertSame(['__uncategorized__' => ['Page phrase']], $this->phrases($world, 'write'));
    }

    public function testARefusedSendIsRetriedByTheNextRequestOnThePhrasePath(): void
    {
        $world = $this->world('writer', ['faults' => [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]]);
        $cache = $this->sharedCache();

        $first = $this->writer($world, 'write', $cache);
        $first->translate('Loose phrase');
        $first->flushPendingRegistrations();
        $this->assertSame([], $this->phrases($world, 'write'));

        $second = $this->writer($world, 'write', $cache);
        $second->translate('Loose phrase');
        $second->flushPendingRegistrations();

        $this->assertSame(['__uncategorized__' => ['Loose phrase']], $this->phrases($world, 'write'));
    }

    private function missOnEveryPath(Client $client)
    {
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
        $world = $this->world('writer');

        $this->missOnEveryPath($this->writer($world, 'write'));

        $this->assertSame(['__uncategorized__' => ['Page path', 'Phrase path']], $this->phrases($world, 'write'));
        $this->assertSame(['UI' => [['Block one', 'Block two']]], $this->blocks($world, 'write'));
    }

    /**
     * GATE-6: a writer does not report. The API stores no hint from a caller
     * that can write, so the absence is shown after drift: the session learns
     * it may write, then the case moves off its key's allow-list, where a hint
     * from it would be stored - the control, sent in that world.
     */
    public function testAWriterReportsNothingEvenOnceTheServerWouldStoreAHint(): void
    {
        $world = $this->world('reporting-writer');
        $client = $this->writer($world, 'ip');
        $this->assertTrue($client->canWrite());

        $this->update($world, ['caller' => $world->moved]);
        $client->translate('Phrase path');
        $client->translatePage('<html><body><p>Page path</p></body></html>');
        $client->flushPendingRegistrations();
        $this->assertSame([], $this->hints($world), 'the writer reported nothing');

        $this->postHint($world, 'ip');
        $this->assertCount(1, $this->hints($world), 'control: in the drifted world a hint from this key is stored');
    }

    /**
     * GATE-7, the non-writer's direction: a server SDK's other lane is its log,
     * never a report (HINT-2), so nothing is reported - by a key whose hint the
     * API stores, as the control at the end shows in the same world.
     */
    public function testANonWriterReportsNothingFromAnyPath(): void
    {
        $world = $this->world('reporting-reader');

        $this->missOnEveryPath($this->writer($world, 'ip'));
        $this->assertSame([], $this->hints($world));
        $this->assertSame([], $this->phrases($world, 'ip'));

        $this->postHint($world, 'ip');
        $this->assertCount(1, $this->hints($world), 'control: a hint from the non-writing key is stored');
    }

    /**
     * GATE-6 and GATE-7: a non-writer registers nothing from any path. Shown
     * where the API would accept the writes: the session learns it may not
     * write, then the case moves to an address on its key's allow-list.
     */
    public function testANonWriterHoldsBackEveryPathOnceTheServerWouldAcceptIt(): void
    {
        $world = $this->world('held-back');
        $client = $this->writer($world, 'ip');
        $this->assertFalse($client->canWrite());

        $this->update($world, ['caller' => $world->moved]);
        $this->missOnEveryPath($client);
        $this->assertSame([], $this->phrases($world, 'ip'));
        $this->assertSame([], $this->blocks($world, 'ip'));

        $this->missOnEveryPath($this->writer($world, 'ip'));
        $this->assertNotSame([], $this->phrases($world, 'ip'), 'control: in the drifted world a session that learns it may write does');
    }

    /**
     * REG-9: chunked to the limit the API advertises, on the phrase and the
     * block path. The clone advertises 3 and answers 422 to an oversized batch,
     * so anything sent unchunked would be missing from the catalog.
     */
    public function testEveryItemIsAcceptedWhenTheServerAdvertisesASmallBatchLimit(): void
    {
        $world = $this->world('writer');

        $client = $this->writer($world, 'write');
        $this->assertSame(3, $client->getProject()['langsys_settings']['translatable_items']['batch_limit'], 'the clone advertises a limit of 3');
        for ($i = 1; $i <= 7; $i++) {
            $client->translate("Phrase $i");
            $client->translateContentBlock("<div><p>Block $i a</p><p>Block $i b</p></div>", 'UI');
        }
        $client->flushPendingRegistrations();

        $this->assertCount(7, $this->phrases($world, 'write')['__uncategorized__']);
        $this->assertCount(7, $this->blocks($world, 'write')['UI']);
    }

    /**
     * REG-10: no throw into the caller, and the result says what happened - a
     * refused send is not success, an accepted one is.
     */
    public function testAFailedSendReportsFailureAndStoresNothing(): void
    {
        $world = $this->world('writer', ['faults' => [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]]);

        $client = $this->writer($world, 'write');
        $client->translate('Refused');
        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame([], $this->phrases($world, 'write'));
    }

    public function testAnAcceptedSendReportsSuccess(): void
    {
        $world = $this->world('writer');

        $client = $this->writer($world, 'write');
        $client->translate('Accepted');
        $result = $client->flushPendingRegistrations();

        $this->assertTrue($result['success']);
        $this->assertSame(['__uncategorized__' => ['Accepted']], $this->phrases($world, 'write'));
    }

    public function testASkippedWriteIsNotReportedAsSuccess(): void
    {
        $world = $this->world('reader');

        $client = $this->writer($world, 'read');
        $client->translate('Skipped');
        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame('not_write_enabled', $result['reason']);
        $this->assertSame([], $this->phrases($world, 'read'));
    }

    /**
     * REG-10: while the catalog cannot be read no miss is decided and nothing
     * is queued; the flush reports that skip by name, distinct from a refused
     * send and from a session that may not write.
     */
    public function testAFlushWhileTheCatalogIsUnavailableNamesIt(): void
    {
        $world = $this->world('writer', ['faults' => [['method' => 'GET', 'path' => '/translations', 'status' => 500, 'times' => 5]]]);

        $client = $this->writer($world, 'write');
        $client->translate('Undecided');
        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame('catalog_unavailable', $result['reason']);
        $this->assertSame([], $this->phrases($world, 'write'));
    }

    /**
     * Once a later read succeeds the catalog is available again, and a flush
     * with nothing to send is a success.
     */
    public function testARecoveredCatalogIsNoLongerReportedUnavailable(): void
    {
        $world = $this->world('known', ['faults' => [['method' => 'GET', 'path' => '/translations', 'status' => 500]]]);

        $client = $this->clockedClient($world);

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
        $world = $this->world('known', ['faults' => [['method' => 'GET', 'path' => '/translations', 'status' => 500]]]);
        $cache = $this->sharedCache();

        $client = $this->writer($world, 'write', $cache);
        $this->assertSame('Known', $client->translate('Known'));

        $other = $this->writer($world, 'write', $cache);
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
        $world = $this->world('writer', ['faults' => [['method' => 'GET', 'path' => '/translations', 'status' => 500]]]);

        $client = $this->clockedClient($world);
        $client->translate('Undecided');
        $this->assertSame('catalog_unavailable', $client->flushPendingRegistrations()['reason']);

        $client->resetRequestState();
        $this->assertTrue($client->flushPendingRegistrations()['success'], 'this request read no catalog');

        $client->resetRequestState();
        $client->setLocale('es-es');
        $client->now += 1;
        $client->translate('Undecided');
        $this->assertSame('catalog_unavailable', $client->flushPendingRegistrations()['reason'], 'inside the window the catalog is still unavailable');
    }

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

    public function testASendTheServerRefusesIsDistinctFromBothSkips(): void
    {
        $world = $this->world('writer', ['faults' => [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]]);

        $client = $this->writer($world, 'write');
        $client->translate('Refused');

        $this->assertSame('send_failed', $client->flushPendingRegistrations()['reason']);
    }

    public function testAWriteEnabledRegistrationSucceedsAndReachesTheNextCatalogRead(): void
    {
        $world = $this->world('writer');

        $client = $this->writer($world, 'write');
        $client->translate('Arrives');
        $result = $client->flushPendingRegistrations();

        $this->assertTrue($result['success']);
        $this->assertNull($result['reason']);

        $next = $this->client($world->key('write'), $world->project());
        $this->assertArrayHasKey('Arrives', $next->translations()->getTranslationMap('es-es')['__uncategorized__']);
    }
}
