<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * MARK-4: a marked host inside a walked unit contributes no tokens to it. It is
 * a unit of its own, registered once on its own terms, on the page path and on
 * the content-block path alike.
 */
class MarkedHostExcisionTest extends TestCase
{
    const UNIT = '<p>Outer intro <span data-ls-contentblock="%s">Inner one <b>two</b></span> <span data-ls-phrase>Buy <b>now</b></span> outer outro</p>';

    /**
     * @var MockHttpClient
     */
    private $http;

    private function client(array $catalog = ['UI' => []])
    {
        $this->http = new MockHttpClient();
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'write', 'write_enabled' => true]]);
        $this->http->setResponse('GET', 'translations', ['data' => $catalog]);
        $this->http->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache()]);
        $reflection = new \ReflectionClass($client);
        foreach (['http', 'translations', 'translatableItems'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            if ($property === 'http') {
                $prop->setValue($client, $this->http);
                continue;
            }
            $resource = $prop->getValue($client);
            $inner = (new \ReflectionClass($resource))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($resource, $this->http);
        }
        $client->setLocale('es-es');

        return $client;
    }

    private function render(Client $client, $path, $html)
    {
        return $path === 'page'
            ? $client->translatePage('<html><body>' . $html . '</body></html>', 'UI')
            : $client->translateContentBlock($html, 'UI');
    }

    /**
     * What a render registers, as phrases and blocks' token lists.
     */
    private function registered($path, $html): array
    {
        $client = $this->client();
        $this->render($client, $path, $html);
        $this->http->clearRequests();
        $client->flushPendingRegistrations();

        $phrases = [];
        $blocks = [];
        foreach ($this->http->getRequests() as $request) {
            foreach (isset($request['data']['translatable_items']) ? $request['data']['translatable_items'] : [] as $item) {
                if (isset($item['phrases'])) {
                    $blocks[] = array_column($item['phrases'], 'phrase');
                } else {
                    $phrases[] = $item['phrase'];
                }
            }
        }
        sort($phrases);
        sort($blocks);

        return ['phrases' => $phrases, 'blocks' => $blocks];
    }

    public function pathProvider(): array
    {
        return ['page path' => ['page'], 'content-block path' => ['block']];
    }

    /**
     * @dataProvider pathProvider
     */
    public function testNestedHostsAreExcisedAndRegisterOnceOnTheirOwn($path): void
    {
        $this->assertSame([
            'phrases' => ['Buy {m0o}now{m0c}'],
            'blocks' => [['Inner one', 'two'], ['Outer intro', 'outer outro']],
        ], $this->registered($path, sprintf(self::UNIT, '9f2c')));
    }

    /**
     * Control: an opted-out block marker is not a host, so its text folds into
     * the enclosing unit. Without it, a walker that drops every nested element
     * would pass the test above.
     *
     * @dataProvider pathProvider
     */
    public function testAnOptedOutMarkerFoldsIntoTheUnit($path): void
    {
        $this->assertSame([
            'phrases' => ['Buy {m0o}now{m0c}'],
            'blocks' => [['Outer intro', 'Inner one', 'two', 'outer outro']],
        ], $this->registered($path, sprintf(self::UNIT, 'false')));
    }

    /**
     * Each unit renders from its own translation, and the markup survives.
     *
     * @dataProvider pathProvider
     */
    public function testEachUnitRendersFromItsOwnTranslation($path): void
    {
        $parser = new HtmlParser();
        $client = $this->client(['UI' => [
            $parser->generateCustomId('UI', ['Outer intro', 'outer outro']) => ['Outer intro' => 'Intro fuera', 'outer outro' => 'cierre fuera'],
            $parser->generateCustomId('UI', ['Inner one', 'two']) => ['Inner one' => 'Dentro uno', 'two' => 'dos'],
            'Buy {m0o}now{m0c}' => 'Compra {m0o}ya{m0c}',
        ]]);

        $rendered = $this->render($client, $path, sprintf(self::UNIT, 'true'));

        foreach (['Intro fuera', 'cierre fuera', 'Dentro uno <b>dos</b>', 'Compra <b>ya</b>'] as $expected) {
            $this->assertStringContainsString($expected, $rendered);
        }
        $this->assertFalse($client->hasPendingRegistrations(), 'every unit was found in the catalog');
    }

    /**
     * A phrase host inside a phrase host: the outer phrase keeps the inner host
     * as one opaque token pair, the inner registers on its own, and both render.
     *
     * @dataProvider pathProvider
     */
    public function testAPhraseHostNestedInAPhraseHost($path): void
    {
        $html = '<p data-ls-phrase>Save <span data-ls-phrase>ten <b>percent</b></span> today</p>';

        $this->assertSame([
            'phrases' => ['Save {m0o}{m0c} today', 'ten {m0o}percent{m0c}'],
            'blocks' => [],
        ], $this->registered($path, $html));

        $client = $this->client(['UI' => [
            'Save {m0o}{m0c} today' => 'Ahorra {m0o}{m0c} hoy',
            'ten {m0o}percent{m0c}' => 'diez {m0o}por ciento{m0c}',
        ]]);
        $rendered = $this->render($client, $path, $html);

        $this->assertStringContainsString('Ahorra <span data-ls-phrase>diez <b>por ciento</b></span> hoy', $rendered);
    }

    /**
     * A block host inside a phrase host is translated before the phrase
     * rebuilds its markup, so the rebuilt copy carries the translation.
     *
     * @dataProvider pathProvider
     */
    public function testABlockHostNestedInAPhraseHost($path): void
    {
        $html = '<p data-ls-phrase>Save <span data-ls-contentblock>ten <b>percent</b></span> today</p>';
        $parser = new HtmlParser();

        $client = $this->client(['UI' => [
            'Save {m0o}{m0c} today' => 'Ahorra {m0o}{m0c} hoy',
            $parser->generateCustomId('UI', ['ten', 'percent']) => ['ten' => 'diez', 'percent' => 'por ciento'],
        ]]);
        $rendered = $this->render($client, $path, $html);

        $this->assertStringContainsString('Ahorra <span data-ls-contentblock>diez <b>por ciento</b></span> hoy', $rendered);
        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * A declared block holding one phrase stays a block (MARK-3): the
     * declaration outranks the TOK-6 shape.
     *
     * @dataProvider pathProvider
     */
    public function testADeclaredBlockOfOnePhraseStaysABlock($path): void
    {
        $this->assertSame(
            ['phrases' => [], 'blocks' => [['Hello']]],
            $this->registered($path, '<div data-ls-contentblock><p>Hello</p></div>')
        );
    }

    /**
     * The enclosing unit's render leaves a nested host alone: its placeholder
     * is filled by its own translation, not interpolated first by the outer
     * unit, which would leave the inner lookup nothing to match.
     *
     * @dataProvider pathProvider
     */
    public function testTheEnclosingRenderLeavesANestedHostToItsOwnTranslation($path): void
    {
        $html = '<p>Outer intro <span data-ls-contentblock>Hello {name} <b>again</b></span> outer outro</p>';
        $parser = new HtmlParser();

        $client = $this->client(['UI' => [
            $parser->generateCustomId('UI', ['Outer intro', 'outer outro']) => ['Outer intro' => 'Intro fuera', 'outer outro' => 'cierre fuera'],
            $parser->generateCustomId('UI', ['Hello {name}', 'again']) => ['Hello {name}' => 'Hola {name}', 'again' => 'otra vez'],
        ]]);

        $rendered = $path === 'page'
            ? $client->translatePage('<html><body>' . $html . '</body></html>', 'UI', [], ['name' => 'Sarah'])
            : $client->translateContentBlock($html, 'UI', ['name' => 'Sarah']);

        $this->assertStringContainsString('Hola Sarah <b>otra vez</b>', $rendered);
        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * A nested host's attributes are its own: registered under its category,
     * not also under the enclosing phrase's.
     */
    public function testANestedHostsAttributesAreItsOwn(): void
    {
        $client = $this->client();
        $client->translatePage('<html><body><p data-ls-phrase>Save <span data-ls-phrase data-langsys-category="Promo" title="Deal">ten</span> today</p></body></html>', 'UI');

        $pending = array_map(function ($item) {
            return $item['category'] . ': ' . $item['phrase'];
        }, array_values($client->getPendingPhrases()));
        sort($pending);

        $this->assertSame(['Promo: Deal', 'Promo: ten', 'UI: Save {m0o}{m0c} today'], $pending);
    }
}
