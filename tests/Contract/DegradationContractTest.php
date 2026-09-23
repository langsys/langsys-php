<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * WIRE-4 against the contract fixture: a catalog read the server refuses, or a
 * connection it drops, degrades every rendering path to source and queues
 * nothing - so the outage leaves nothing behind in the server's state.
 */
class DegradationContractTest extends ContractTestCase
{
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
        $this->seedProject(
            ['k-write' => ['type' => 'write']],
            ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]],
            [],
            [array_merge(['method' => 'GET', 'path' => '/translations', 'times' => 5], $fault)]
        );

        $client = $this->client('k-write');
        $client->setLocale('es-es');

        $this->assertSame('Hello', $client->translate('Hello'));
        $this->assertSame('Unknown', $client->translate('Unknown'));
        $this->assertStringContainsString('<p>Block</p>', $client->translateContentBlock('<div><p>Block</p><p>Two</p></div>', 'UI'));
        $this->assertStringContainsString('<p>Page text</p>', $client->translatePage('<html><body><p>Page text</p></body></html>'));
        $client->flushPendingRegistrations();

        $this->assertSame([[null, 'Hello']], $this->registeredPhrases(), 'only the seeded phrase; the outage registered nothing');
        $this->assertSame([], $this->registeredBlocks());
    }

    /**
     * WIRE-2: an empty 204 - no body, no content type - is a status to branch
     * on, not a body to parse. A write answered 204 was accepted; a catalog read
     * answered 204 carries no catalog, so it degrades like any failed read.
     */
    public function testAnEmpty204IsAcceptedOnAWriteAndDegradesOnARead(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], [], [], [['method' => 'POST', 'path' => '/translatable-items', 'status' => 204]]);
        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->translate('Sent');

        $this->assertTrue($client->flushPendingRegistrations()['success'], 'a 204 on a write is the server accepting it');

        $this->seedProject(['k-write' => ['type' => 'write']], ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]], [], [['method' => 'GET', 'path' => '/translations', 'status' => 204]]);
        $client = $this->client('k-write');
        $client->setLocale('es-es');

        $this->assertSame('Unknown', $client->translate('Unknown'));
        $client->flushPendingRegistrations();
        $this->assertSame([[null, 'Hello']], $this->registeredPhrases(), 'an empty read queued nothing');
    }

    public function testControlTheSameCatalogTranslatesWhenTheReadSucceeds(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');

        $this->assertSame('Hola', $client->translate('Hello'));
    }
}
