<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * TOK-6 on the content-block path: a fragment is one unit, and it registers
 * as a phrase when its one token is its one text node - as the JS core's
 * <Translate> host does - and as a content block otherwise.
 *
 * translateContentBlock(), registerContentBlock() and createContentBlocks()
 * all read the fragment this way. A caller-supplied custom id names a block
 * explicitly and registers one.
 */
class ContentBlockUnitTest extends TestCase
{
    /**
     * @var MockHttpClient
     */
    private $http;

    private function client(array $catalog)
    {
        $this->http = new MockHttpClient();
        $this->http->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $this->http->setResponse('GET', 'translations', ['data' => $catalog]);
        $this->http->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache()]);

        $reflection = new \ReflectionClass($client);
        $prop = $reflection->getProperty('http');
        $prop->setAccessible(true);
        $prop->setValue($client, $this->http);

        foreach (['translations', 'translatableItems'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $resource = $prop->getValue($client);
            $inner = (new \ReflectionClass($resource))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($resource, $this->http);
        }

        $client->setLocale('es-es');

        return $client;
    }

    private function postedItems(): array
    {
        $items = [];
        foreach ($this->http->getRequests() as $request) {
            if ($request['method'] === 'POST' && isset($request['data']['translatable_items'])) {
                foreach ($request['data']['translatable_items'] as $item) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    public function testAOnePhraseFragmentTranslatesAsAPhrase(): void
    {
        $client = $this->client(['UI' => ['Hello' => 'Hola']]);

        $this->assertSame('<p>Hola</p>', $client->translateContentBlock('<p>Hello</p>', 'UI'));
        $this->assertFalse($client->hasPendingRegistrations(), 'a phrase in the catalog is not registered again');
    }

    public function testAOnePhraseFragmentMissRegistersThePhrase(): void
    {
        $client = $this->client(['UI' => []]);

        $rendered = $client->translateContentBlock('<div class="card"><p>Hello</p></div>', 'UI');

        $this->assertSame('<div class="card"><p>Hello</p></div>', $rendered, 'no block id is stamped on a phrase');
        $this->assertSame([], $client->getPendingContentBlocks());
        $this->assertSame(['Hello'], array_values(array_map(function ($pending) {
            return $pending['phrase'];
        }, $client->getPendingPhrases())));
    }

    /**
     * The phrase is written into its text node in place: the markup around it
     * and the node's padding survive.
     */
    public function testThePhraseIsWrittenBackInPlace(): void
    {
        $client = $this->client(['UI' => ['Buy now' => 'Compra ya']]);

        $this->assertMatchesRegularExpression(
            '/^<div class="x"><strong>[\s\x{00A0}]Compra ya[\s\x{00A0}]<\/strong><br><\/div>$/u',
            $client->translateContentBlock("<div class=\"x\"><strong>\u{00A0}Buy now\u{00A0}</strong><br></div>", 'UI')
        );
    }

    public function testParamsFillAOnePhraseFragmentAndTheQueueHoldsTheRawPhrase(): void
    {
        $client = $this->client(['UI' => []]);

        $this->assertSame(
            '<p>Hello Sarah</p>',
            $client->translateContentBlock('<p>Hello %name%</p>', 'UI', ['name' => 'Sarah'])
        );
        $this->assertSame(['Hello {name}'], array_values(array_map(function ($pending) {
            return $pending['phrase'];
        }, $client->getPendingPhrases())));

        $client = $this->client(['UI' => ['Hello {name}' => 'Hola {name}']]);
        $this->assertSame(
            '<p>Hola Sarah</p>',
            $client->translateContentBlock('<p>Hello %name%</p>', 'UI', ['name' => 'Sarah'])
        );
    }

    /**
     * Control: anything that is not one token in one text node stays a block.
     *
     * @dataProvider blockFragmentProvider
     */
    public function testEveryOtherFragmentIsABlock($html, array $tokens): void
    {
        $client = $this->client(['UI' => []]);
        $client->translateContentBlock($html, 'UI');

        $this->assertSame([], $client->getPendingPhrases());
        $pending = array_values($client->getPendingContentBlocks());
        $this->assertCount(1, $pending);
        $this->assertSame($tokens, $pending[0]['phrases']);
        $this->assertSame((new HtmlParser())->generateCustomId('UI', $tokens), $pending[0]['customId']);
    }

    public function blockFragmentProvider(): array
    {
        return [
            'two text nodes' => ['<p>Hello <b>bold</b></p>', ['Hello', 'bold']],
            'a titled element' => ['<p title="Tooltip">Hello</p>', ['Tooltip', 'Hello']],
            'one attribute token' => ['<img alt="Logo">', ['Logo']],
            'two elements' => ['<p>One</p><p>Two</p>', ['One', 'Two']],
        ];
    }

    public function testRegisterContentBlockRegistersAOnePhraseFragmentAsAPhrase(): void
    {
        $client = $this->client(['UI' => []]);
        $client->registerContentBlock('<p>Hello</p>', 'UI');

        $this->assertSame(
            [['type' => 'phrase', 'phrase' => 'Hello', 'category' => 'UI', 'translatable' => true]],
            $this->postedItems()
        );
    }

    public function testANamedBlockStaysABlock(): void
    {
        $client = $this->client(['UI' => []]);
        $client->registerContentBlock('<p>Hello</p>', 'UI', null, 'hero-title');

        $items = $this->postedItems();
        $this->assertCount(1, $items);
        $this->assertSame('content_block', $items[0]['type']);
        $this->assertSame('hero-title', $items[0]['custom_id']);
    }

    public function testCreateContentBlocksSplitsPhrasesFromBlocks(): void
    {
        $client = $this->client(['UI' => []]);
        $client->translatableItems()->createContentBlocks([
            ['html' => '<p>Hello</p>', 'category' => 'UI'],
            ['html' => '<p>One</p><p>Two</p>', 'category' => 'UI'],
            ['html' => '<p>Named</p>', 'category' => 'UI', 'customId' => 'named-block'],
        ]);

        $items = $this->postedItems();
        $this->assertSame(['phrase', 'content_block', 'content_block'], array_column($items, 'type'));
        $this->assertSame('Hello', $items[0]['phrase']);
        $this->assertSame((new HtmlParser())->generateCustomId('UI', ['One', 'Two']), $items[1]['custom_id']);
        $this->assertSame('named-block', $items[2]['custom_id']);
    }
}
