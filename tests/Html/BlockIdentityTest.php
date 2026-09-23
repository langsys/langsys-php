<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * MARK-1: a rendered block host carries the custom_id it was rendered from.
 * MARK-3: a content-block marker declares a block (bare, empty, true, 1, yes),
 * opts out (false, 0), or is an identity - a stamped custom_id, recognised
 * rather than re-derived, rendered from and never registered.
 */
class BlockIdentityTest extends TestCase
{
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

    public function pathProvider(): array
    {
        return ['page path' => ['page'], 'content-block path' => ['block']];
    }

    /**
     * The first element carrying a content-block marker in rendered HTML.
     */
    private function host($html)
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $found = (new \DOMXPath($doc))->query('//*[@data-ls-contentblock or @data-langsys-contentblock]');

        return $found->length > 0 ? $found->item(0) : null;
    }

    // MARK-1

    /**
     * The spec's test: render, then run the tokenizer over the same subtree
     * independently. The stamp equals the id the tokenizer derives - here
     * from the rendered subtree, since nothing is translated yet.
     *
     * @dataProvider pathProvider
     */
    public function testTheStampEqualsTheIdTheTokenizerDerives($path): void
    {
        $rendered = $this->render($this->client(), $path, '<p>Hello <b>World</b> again</p>');
        $host = $this->host($rendered);

        $this->assertNotNull($host, 'the rendered host is stamped');
        $this->assertSame(
            (new HtmlParser())->generateCustomId('UI', (new HtmlParser())->unitTokens($host)['tokens']),
            $host->getAttribute('data-ls-contentblock')
        );
    }

    /**
     * Translated, the stamp is still the id of the source it was rendered
     * from, as the tokenizer derives it from the source subtree.
     *
     * @dataProvider pathProvider
     */
    public function testATranslatedHostCarriesItsSourceId($path): void
    {
        $source = '<p>Hello <b>World</b> again</p>';
        $sourceHost = $this->host(str_replace('<p>', '<p data-ls-contentblock>', $source));
        $id = (new HtmlParser())->generateCustomId('UI', (new HtmlParser())->unitTokens($sourceHost)['tokens']);

        $rendered = $this->render($this->client(['UI' => [$id => ['Hello' => 'Hola', 'World' => 'Mundo', 'again' => 'otra vez']]]), $path, $source);

        $this->assertStringContainsString('Hola', $rendered);
        $this->assertSame($id, $this->host($rendered)->getAttribute('data-ls-contentblock'));
    }

    // MARK-3

    /**
     * Every declaration spelling registers the same one block, and the render
     * stamps the declaration with its id.
     *
     * @dataProvider declarationProvider
     */
    public function testEveryDeclarationRegistersTheSameBlock($attribute, $value): void
    {
        $markup = '<div ' . $attribute . ($value === null ? '' : '="' . $value . '"') . '><p>Alpha</p><p>Beta</p></div>';
        $id = (new HtmlParser())->generateCustomId('UI', ['Alpha', 'Beta']);

        foreach (['page', 'block'] as $path) {
            $client = $this->client();
            $rendered = $this->render($client, $path, $markup);

            $pending = array_values($client->getPendingContentBlocks());
            $this->assertCount(1, $pending, $path);
            $this->assertSame($id, $pending[0]['customId'], $path);
            $this->assertSame($id, $this->host($rendered)->getAttribute($attribute), $path . ': the declaration now carries its id');
        }
    }

    public function declarationProvider(): array
    {
        $rows = [];
        foreach (HtmlParser::CONTENT_BLOCK_MARKERS as $attribute) {
            foreach ([null, '', 'true', 'TRUE', '1', 'yes', ' yes '] as $value) {
                $rows[$attribute . '=' . var_export($value, true)] = [$attribute, $value];
            }
        }

        return $rows;
    }

    /**
     * An opt-out walks the element as ordinary markup: its paragraphs are
     * their own units, and nothing is stamped on it.
     *
     * @dataProvider optOutProvider
     */
    public function testAnOptOutIsOrdinaryMarkup($attribute, $value): void
    {
        $client = $this->client();
        $rendered = $client->translatePage('<html><body><div ' . $attribute . '="' . $value . '"><p>Alpha</p><p>Beta</p></div></body></html>', 'UI');

        $this->assertSame([], $client->getPendingContentBlocks());
        $this->assertSame(['Alpha', 'Beta'], array_values(array_column($client->getPendingPhrases(), 'phrase')));
        $this->assertStringContainsString($attribute . '="' . $value . '"', $rendered);
    }

    public function optOutProvider(): array
    {
        $rows = [];
        foreach (HtmlParser::CONTENT_BLOCK_MARKERS as $attribute) {
            foreach (['false', 'FALSE', '0'] as $value) {
                $rows[$attribute . '=' . $value] = [$attribute, $value];
            }
        }

        return $rows;
    }

    /**
     * Any other value is the host's custom_id: nothing is registered, the
     * host renders from the catalog entry filed under it, and the stamp is
     * kept as written.
     *
     * @dataProvider identityProvider
     */
    public function testAStampedIdIsRecognisedNotRederived($path, $attribute): void
    {
        $markup = '<div ' . $attribute . '="abc123"><p>Alpha</p><p>Beta</p></div>';
        $derived = (new HtmlParser())->generateCustomId('UI', ['Alpha', 'Beta']);

        $client = $this->client(['UI' => [
            'abc123' => ['Alpha' => 'Alfa', 'Beta' => 'Beta ES'],
            $derived => ['Alpha' => 'WRONG', 'Beta' => 'WRONG'],
        ]]);
        $rendered = $this->render($client, $path, $markup);

        $this->assertStringContainsString('<p>Alfa</p><p>Beta ES</p>', $rendered, 'rendered from the stamped id');
        $this->assertStringNotContainsString('WRONG', $rendered, 'not from the derived one');
        $this->assertStringContainsString($attribute . '="abc123"', $rendered);
        $this->assertFalse($client->hasPendingRegistrations(), 'nothing is registered for the host');
    }

    /**
     * A stamped id the catalog does not hold renders source and still
     * registers nothing: the renderer that stamped it registered it.
     *
     * @dataProvider identityProvider
     */
    public function testAnUnknownStampedIdRendersSourceAndRegistersNothing($path, $attribute): void
    {
        $client = $this->client();
        $rendered = $this->render($client, $path, '<div ' . $attribute . '="abc123"><p>Alpha</p><p>Beta</p></div>');

        $this->assertStringContainsString('<p>Alpha</p><p>Beta</p>', $rendered);
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function identityProvider(): array
    {
        $rows = [];
        foreach (['page', 'block'] as $path) {
            foreach (HtmlParser::CONTENT_BLOCK_MARKERS as $attribute) {
                $rows[$path . ' ' . $attribute] = [$path, $attribute];
            }
        }

        return $rows;
    }

    /**
     * An author's opt-out on an element that registers as a block by its own
     * shape is kept: the render never turns it into an id.
     */
    public function testAnOptOutOnABlockShapedUnitIsKept(): void
    {
        $client = $this->client();
        $rendered = $client->translatePage('<html><body><p data-ls-contentblock="false">Hello <b>World</b></p></body></html>', 'UI');

        $this->assertStringContainsString('<p data-ls-contentblock="false">', $rendered);
    }

    /**
     * A stamped id is the only id read for its host: a catalog entry under
     * the id its content would derive, current or legacy, is not used.
     *
     * @dataProvider pathProvider
     */
    public function testAStampedHostIsNotResolvedByItsContent($path): void
    {
        $legacy = md5(implode('|', ['UI', 'Alpha', 'Beta']));
        $client = $this->client(['UI' => [$legacy => ['Alpha' => 'WRONG', 'Beta' => 'WRONG']]]);

        $rendered = $this->render($client, $path, '<div data-ls-contentblock="abc123"><p>Alpha</p><p>Beta</p></div>');

        $this->assertStringContainsString('<p>Alpha</p><p>Beta</p>', $rendered);
        $this->assertStringNotContainsString('WRONG', $rendered);
    }
}
