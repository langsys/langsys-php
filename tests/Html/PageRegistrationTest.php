<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for how translatePage() registers what it discovers.
 *
 * Page registration used to run INLINE, mid-render: one POST for the phrases,
 * one POST per new content block, then a cache clear and a refetch. A page with
 * eight new blocks blocked on ten round trips before a byte reached the user,
 * while translate() - the other entry point to the same catalog - made none.
 *
 * It now routes through the same pending queue, so a host with a post-response
 * hook can flush after the response, and the per-block N+1 collapses into the
 * batched flush that already existed.
 */
class PageRegistrationTest extends TestCase
{
    /**
     * @var MockHttpClient
     */
    protected $mock;

    protected function makeClient($keyType = 'write')
    {
        $this->mock = new MockHttpClient();
        $this->mock->setResponse('GET', 'authorize-project/p', ['data' => ['key_type' => $keyType]]);
        $this->mock->setResponse('GET', 'translations', ['data' => ['home' => []]]);

        $client = new Client('k', 'p', ['cache' => new NullCache()]);
        $reflection = new \ReflectionClass($client);

        $http = $reflection->getProperty('http');
        $http->setAccessible(true);
        $http->setValue($client, $this->mock);

        foreach (['translations', 'translatableItems'] as $prop) {
            $p = $reflection->getProperty($prop);
            $p->setAccessible(true);
            $resource = $p->getValue($client);

            $rr = new \ReflectionClass($resource);
            $rh = $rr->getProperty('http');
            $rh->setAccessible(true);
            $rh->setValue($resource, $this->mock);
        }

        $client->setLocale('es-es');

        return $client;
    }

    protected function countRequests($method)
    {
        $n = 0;

        foreach ($this->mock->getRequests() as $request) {
            if ($request['method'] === $method) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * A page with several new content blocks and several new phrases.
     */
    protected function pageWithNewContent()
    {
        $body = '';

        for ($i = 1; $i <= 4; $i++) {
            $body .= '<div><p><strong>Bold ' . $i . '</strong> and text ' . $i . '</p></div>';
        }

        for ($i = 1; $i <= 3; $i++) {
            $body .= '<p>Phrase ' . $i . '</p>';
        }

        return '<!DOCTYPE html><html><head><title>T</title></head><body>' . $body . '</body></html>';
    }

    /**
     * The load-bearing assertion: rendering must not block on registration.
     */
    public function testTranslatePageIssuesNoRegistrationRequestsDuringRender()
    {
        $client = $this->makeClient();

        $client->translatePage($this->pageWithNewContent(), 'home');

        $this->assertSame(
            0,
            $this->countRequests('POST'),
            'translatePage() must queue rather than register mid-render'
        );

        $this->assertTrue(
            $client->hasPendingRegistrations(),
            'The discovered items must actually be queued, not dropped'
        );
    }

    /**
     * Guards the N+1: four new blocks must not become four requests.
     */
    public function testQueuedPageRegistrationsAreBatchedOnFlush()
    {
        $client = $this->makeClient();

        $client->translatePage($this->pageWithNewContent(), 'home');
        $result = $client->flushPendingRegistrations();

        $this->assertSame(4, $result['content_blocks']);
        $this->assertSame(4, $result['phrases']);

        // One batched POST for phrases, one for content blocks.
        $this->assertSame(2, $this->countRequests('POST'));
    }

    /**
     * The raw, placeholder-bearing content is what reaches the catalog.
     */
    public function testQueuedPageRegistrationsCarryTheDiscoveredContent()
    {
        $client = $this->makeClient();

        $client->translatePage(
            '<!DOCTYPE html><html><head><title>T</title></head><body><p>Hello {name}</p></body></html>',
            'home',
            [],
            ['name' => 'Sarah']
        );
        $client->flushPendingRegistrations();

        $body = json_encode($this->mock->getRequests(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Hello {name}', $body);
        $this->assertStringNotContainsString('Sarah', $body, 'Interpolated values must never be registered');
    }

    /**
     * A read-only key must issue no requests, and the flush must drop the queue
     * silently rather than erroring.
     *
     * NOTE the positive assertion first. "No requests were made" passes when the
     * key was correctly skipped AND, identically, when discovery silently found
     * nothing at all - so on its own it would keep passing through a refactor
     * that broke extraction entirely. Asserting the items were queued proves the
     * capture point is live before asserting what did not happen to them.
     */
    public function testReadOnlyKeyIssuesNoRequestsAndClearsQuietly()
    {
        $client = $this->makeClient('read');

        $client->translatePage($this->pageWithNewContent(), 'home');

        // Positive: discovery ran and produced work.
        $this->assertTrue($client->hasPendingRegistrations());
        $this->assertNotEmpty($client->getPendingPhrases());
        $this->assertNotEmpty($client->getPendingContentBlocks());

        // Absence: none of it cost the render anything.
        $this->assertSame(0, $this->countRequests('POST'));

        $result = $client->flushPendingRegistrations();

        $this->assertSame(0, $result['phrases']);
        $this->assertSame(0, $result['content_blocks']);
        $this->assertSame(0, $this->countRequests('POST'));
        $this->assertFalse($client->hasPendingRegistrations());
    }
}
