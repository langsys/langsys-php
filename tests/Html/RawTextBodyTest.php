<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * A script or style body comes back byte for byte on every path that parses
 * HTML. HTML ends such a body only at its own end tag, so
 * `el.innerHTML = '<p>hi</p>'` is one string of JavaScript; libxml2's parser
 * (2.9) ends it at any end tag and drops the tag, which rewrote a page's own
 * code. An excluded element in a rich phrase is restored from source the same
 * way (TOK-1).
 */
class RawTextBodyTest extends TestCase
{
    private function client(array $catalog = [])
    {
        $http = new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us']]);
        $http->setResponse('GET', 'translations', ['data' => ['UI' => $catalog]]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false]);
        $reflection = new \ReflectionClass($client);
        foreach (['http', 'translations', 'translatableItems'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            if ($property === 'http') {
                $prop->setValue($client, $http);
                continue;
            }
            $resource = $prop->getValue($client);
            $inner = (new \ReflectionClass($resource))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($resource, $http);
        }
        $client->setLocale('es-es');

        return $client;
    }

    public function bodyProvider()
    {
        return [
            'an innerHTML string' => ['<script>el.innerHTML = \'<p>hi</p>\';</script>'],
            'a template literal' => ['<script>const t = `<div class="x"><span>${n}</span></div>`;</script>'],
            'JSON-LD' => ['<script type="application/ld+json">{"desc": "<b>bold</b>"}</script>'],
            'a style comment' => ['<style>/* </p> */ p{color:red}</style>'],
            'a comparison, the control' => ['<script>if (a < b && c > d) go();</script>'],
        ];
    }

    /**
     * @dataProvider bodyProvider
     */
    public function testAPageKeepsItsScriptAndStyleBodies($body): void
    {
        foreach ([[], ['Hello' => 'Hola']] as $catalog) {
            $page = $this->client($catalog)->translatePage('<html><head><meta charset="utf-8"></head><body><p>Hello</p>' . $body . '</body></html>', 'UI');

            $this->assertStringContainsString($body, $page);
        }
    }

    /**
     * @dataProvider bodyProvider
     */
    public function testABlockKeepsItsScriptAndStyleBodies($body): void
    {
        $html = '<div><p>Hello</p>' . $body . '<p>World</p></div>';

        $this->assertStringContainsString($body, $this->client()->translateContentBlock($html, 'UI'));
        $this->assertSame(['Hello', 'World'], array_values((new HtmlParser())->extractPhrases($html)), 'and the body is never a phrase');
    }

    /**
     * TOK-1: a script in a rich phrase is one empty pair restored from source,
     * at the top level or inside an excluded element, and no param is filled
     * inside it.
     */
    public function testARichLineRestoresAScriptAsWritten(): void
    {
        foreach (['Run <script>s = "<i>{email}</i>"</script> now', 'Run <b translate="no"><script>s = "<i>{email}</i>"</script></b> now'] as $line) {
            $client = $this->client();

            $this->assertSame($line, $client->translateRich($line, null, 'UI', ['email' => 'ana@example.com'])['html']);
            $this->assertSame(['Run {m0o}{m0c} now'], array_values(array_column($client->getPendingPhrases(), 'phrase')));
        }
    }

    /**
     * A `>` inside a script's attribute does not end its open tag: the body
     * still comes back whole.
     */
    public function testAnAttributeHoldingAGreaterThanSignDoesNotEndTheOpenTag(): void
    {
        $page = $this->client()->translatePage('<html><head><meta charset="utf-8"></head><body><p>Hello</p><script data-note="a>b">t = "</b>";</script></body></html>', 'UI');

        $this->assertStringContainsString('<script data-note="a&gt;b">t = "</b>";</script>', $page);
    }

    /**
     * A script written inside a comment or an attribute value is not an
     * element, and comes back as written too.
     */
    public function testAScriptInACommentOrAnAttributeComesBackAsWritten(): void
    {
        foreach (['<!-- <script>a("</b>")</script> -->', '<div title="<script>x(\'</b>\')</script>">Hi</div>'] as $html) {
            $page = $this->client()->translatePage('<html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', 'UI');

            $this->assertStringContainsString(strpos($html, '<!--') === 0 ? $html : 'x(\'&lt;/b&gt;\')', $page, $html);
        }
    }
}
