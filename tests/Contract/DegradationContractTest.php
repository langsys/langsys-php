<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * WIRE-4 against the contract fixture: a catalog read that fails with a 500,
 * or a connection dropped, degrades every rendering path to source and queues
 * nothing - so the outage leaves nothing behind in what the API holds.
 */
class DegradationContractTest extends ContractTestCase
{
    protected static $seed = 'degradation';

    public function failures()
    {
        return [
            'a 500' => [['status' => 500]],
            'a dropped connection' => [['drop' => true]],
        ];
    }

    /**
     * @dataProvider failures
     */
    public function testEveryPathRendersSourceAndStoresNothing(array $fault): void
    {
        $world = $this->world('hello', ['faults' => [array_merge(['method' => 'GET', 'path' => '/translations', 'times' => 5], $fault)]]);

        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');

        $this->assertSame('Hello', $client->translate('Hello'));
        $this->assertSame('Unknown', $client->translate('Unknown'));
        $this->assertStringContainsString('<p>Block</p>', $client->translateContentBlock('<div><p>Block</p><p>Two</p></div>', 'UI'));
        $this->assertStringContainsString('<p>Page text</p>', $client->translatePage('<html><body><p>Page text</p></body></html>'));
        $client->flushPendingRegistrations();

        // The outage ends, so the faults the client left unspent don't answer the read back.
        $this->update($world, ['faults' => []]);
        $held = $this->held($world->key('write'), $world->project(), 'en-us');
        $this->assertSame(['__uncategorized__' => ['Hello']], $held['phrases'], 'only the seeded phrase; the outage registered nothing');
        $this->assertSame([], $held['blocks']);
    }

    /**
     * WIRE-2: an empty 204 - no body, no content type - is a status to branch
     * on, not a body to parse. A write answered 204 was accepted; a catalog read
     * answered 204 carries no catalog, so it degrades like any failed read.
     * The 204s are the fixture's, as something in front of the API would send.
     */
    public function testAnEmpty204IsAcceptedOnAWriteAndDegradesOnARead(): void
    {
        $world = $this->world('writer', ['faults' => [['method' => 'POST', 'path' => '/translatable-items', 'status' => 204]]]);
        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');
        $client->translate('Sent');

        $this->assertTrue($client->flushPendingRegistrations()['success'], 'a 204 on a write is the server accepting it');

        $world = $this->world('hello', ['faults' => [['method' => 'GET', 'path' => '/translations', 'status' => 204]]]);
        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');

        $this->assertSame('Unknown', $client->translate('Unknown'));
        $client->flushPendingRegistrations();
        $this->assertSame(['__uncategorized__' => ['Hello']], $this->held($world->key('write'), $world->project(), 'en-us')['phrases'], 'an empty read queued nothing');
    }

    public function testControlTheSameCatalogTranslatesWhenTheReadSucceeds(): void
    {
        $world = $this->world('hello');

        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');

        $this->assertSame('Hola', $client->translate('Hello'));
    }
}
