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
     * GATE-6 and GATE-7, the writer's direction: every detection path registers,
     * and nothing is reported.
     */
    public function testAWriterRegistersFromEveryPathAndReportsNothing(): void
    {
        $this->seedReportingCapable();

        $this->missOnEveryPath('k-write');

        $this->assertEqualsCanonicalizing([[null, 'Phrase path'], [null, 'Page path']], $this->registeredPhrases());
        $this->assertSame([['UI', ['Block one', 'Block two']]], $this->registeredBlocks());
        $this->assertSame([], $this->storedHints());
    }

    /**
     * GATE-7, the non-writer's direction: nothing registers from any path. A
     * server SDK's other lane is its log, never a report (HINT-2), so nothing is
     * reported either - against a key the control shows could report.
     */
    public function testANonWriterRegistersNothingFromAnyPathAndReportsNothing(): void
    {
        $this->seedReportingCapable();

        $this->missOnEveryPath('k-reader');

        $this->assertSame([], $this->registeredPhrases());
        $this->assertSame([], $this->registeredBlocks());
        $this->assertSame([], $this->storedHints());
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
        $this->assertSame([], $this->registeredPhrases());
    }
}
