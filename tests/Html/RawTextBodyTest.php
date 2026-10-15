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
     * Only an element is a script or a style. The words written in a comment,
     * an attribute value or a title or textarea body, and a custom element
     * whose name merely starts with one, are text the parser leaves alone, and
     * so is everything after them.
     */
    public function testAMentionOfScriptOrStyleIsNotAnElement(): void
    {
        $page = $this->client(['Hello' => 'Hola'])->translatePage('<html><head><meta charset="utf-8"><!-- critical <style> inlined below --><style>body{margin:0}</style></head><body><p>Hello</p></body></html>', 'UI');
        $this->assertStringContainsString('<!-- critical <style> inlined below --><style>body{margin:0}</style>', $page);
        $this->assertStringContainsString('<p>Hola</p>', $page);

        $page = $this->client(['Hello' => 'Hola'])->translatePage('<html><head><meta charset="utf-8"><meta name="description" content="Learn the <script> tag"><script>var a = "</b>";</script></head><body><p>Hello</p></body></html>', 'UI');
        $this->assertStringContainsString('content="Learn the &lt;script&gt; tag"', $page);
        $this->assertStringContainsString('<script>var a = "</b>";</script>', $page);
        $this->assertStringContainsString('<p>Hola</p>', $page);

        $client = $this->client();
        $block = $client->translateContentBlock('<div><p title="the <style> element">Hello</p><p>World</p><style>p{}</style></div>', 'UI');
        $this->assertStringContainsString('<p title="the &lt;style&gt; element">Hello</p>', $block);
        $this->assertSame([['the <style> element', 'Hello', 'World']], array_values(array_map(function ($b) {
            return $b['phrases'];
        }, $client->getPendingContentBlocks())));

        foreach (['<div><style-guide><p>Hello</p></style-guide><p>World</p><style>p{color:red}</style></div>', '<div><script-loader>Hello</script-loader><p>World</p><script>go("</b>")</script></div>', '<div><textarea>Type <script> here</script></textarea><p>World</p><script>go("</b>")</script></div>'] as $html) {
            $this->assertSame($html, preg_replace('/ data-ls-contentblock="[0-9a-f]+"/', '', $this->client()->translateContentBlock($html, 'UI')), $html);
        }
    }

    /**
     * A script written in an attribute value stays the value as written,
     * a bare `&` included.
     */
    public function testAScriptWrittenInAnAttributeValueKeepsTheValue(): void
    {
        $page = $this->client()->translatePage('<html><head><meta charset="utf-8"></head><body><div data-x="<script>a && b</script>"><p>Hello</p></div></body></html>', 'UI');

        $this->assertStringContainsString('data-x="&lt;script&gt;a &amp;&amp; b&lt;/script&gt;"', $page);
    }

    /**
     * The markup around a body is read as libxml reads it, so what precedes
     * a script cannot change where it starts or ends: a self-closed script or
     * style, a comment closed by `--!>`, a stray quote in a tag, and a `<`
     * that opens no tag.
     */
    public function testWhatPrecedesABodyIsReadAsTheParserReadsIt(): void
    {
        $body = '<script>el.innerHTML = "<b>x</b>";</script>';
        foreach (['<script src="a.js"/>', '<svg viewBox="0 0 1 1"><defs><style/></defs></svg>', '<!-- nav --!>', '<img src="a.png"" alt="Logo">'] as $before) {
            $page = $this->client(['Hello' => 'Hola'])->translatePage('<html><head><meta charset="utf-8"></head><body>' . $before . '<p>Hello</p>' . $body . '<p>Hello</p></body></html>', 'UI');

            $this->assertStringContainsString('<p>Hola</p>' . $body . '<p>Hola</p>', $page, $before);
        }
    }

    /**
     * A page is written out in UTF-8, which it declares, so a script, JSON-LD
     * or style body keeps its non-ASCII characters as written, where an
     * entity would not be decoded; with `<meta charset>`, with the
     * http-equiv form, and with no charset declared at all.
     */
    public function testAPageKeepsNonAsciiInItsBodiesAsWritten(): void
    {
        $bodies = '<script type="application/ld+json">{"name":"Café Müller"}</script><script>var msg = "¡Hola señor! 😀";</script><style>.q::before{content:"«"}</style>';

        foreach (['<meta charset="utf-8">', '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">', ''] as $meta) {
            $page = $this->client(['Hello' => 'Hola'])->translatePage('<!DOCTYPE html><html><head>' . $meta . $bodies . '</head><body><p>Hello</p><p>Café</p></body></html>', 'UI');

            $this->assertStringContainsString($bodies, $page, $meta);
            $this->assertStringContainsString('<p>Café</p>', $page, $meta);
            $this->assertSame(0, strpos($page, '<!DOCTYPE html>' . "\n" . '<html'), 'the doctype as written, and no encoding hint: ' . $meta);
        }
    }

    /**
     * Every charset a page declares is UTF-8 once it is written out, so the
     * declaration matches the bytes: on a page without a `<head>`, as a
     * minifier leaves one, and on one declaring it twice.
     */
    public function testEveryCharsetThePageDeclaresIsUtf8(): void
    {
        foreach ([
            "<!DOCTYPE html><html lang=\"de\"><meta charset=\"windows-1252\"><title>Caf\xE9</title><p>Hello world</p><p>M\xFCller</p></html>",
            "<html><meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\"><p>Hello world</p><p>M\xFCller</p></html>",
            "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\"><meta charset=\"iso-8859-1\"></head><body><p>M\xFCller</p></body></html>",
        ] as $html) {
            $page = $this->client()->translatePage($html, 'UI');

            $this->assertStringContainsString('<p>Müller</p>', $page, $html);
            $this->assertSame(0, preg_match('/charset=["\']?(?!utf-8)[a-z0-9-]+/i', $page), 'no other charset declared: ' . $page);
        }
    }

    /**
     * A doctype with public and system identifiers is written back as it was.
     */
    public function testALegacyDoctypeIsWrittenBack(): void
    {
        $doctype = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
        $page = $this->client()->translatePage($doctype . '<html><head></head><body><p>Hello</p></body></html>', 'UI');

        $this->assertSame(0, strpos($page, $doctype . "\n" . '<html'));
    }

    /**
     * A fragment that leaves a script or style open ends it at the fragment's
     * end, as a browser does, and none of the SDK's own wrapper reaches it.
     */
    public function testAFragmentThatLeavesABodyOpenEndsItAtTheFragmentsEnd(): void
    {
        $block = $this->client(['Hello' => 'Hola'])->translateContentBlock('<div><p>Hello</p><p>World</p><script>track("x");</div>', 'UI');
        $this->assertStringEndsWith('<script>track("x");</div></script></div>', $block, 'its own text is its own');

        foreach (['<p>Hello</p><p>World</p><script>track("x");' => '<script>track("x");</script>', '<p>Hello</p><p>World</p><style>p{color:red}' => '<style>p{color:red}</style>'] as $html => $body) {
            $out = $this->client(['Hello' => 'Hola'])->translateContentBlock($html, 'UI');
            $this->assertStringEndsWith($body, $out, $html);
            $this->assertStringNotContainsString('ls-fragment', $out);
        }

        $this->assertSame('{name} says hi<style>p{}</style>', \Langsys\SDK\Html\ValueMarkers::placeholderHtml('<!--ls:name-->Bob<!--/ls--> says hi<style>p{}'));
        $this->assertSame('Run <script>go()</script>', $this->client()->translateRich('Run <script>go()', null, 'UI')['html']);
        $this->assertSame('<p>Hi</p>', \Langsys\SDK\Html\ValueMarkers::placeholderHtml('<p>Hi</p>'), 'a closed fragment is untouched');
        $this->assertStringNotContainsString('ls-fragment', $this->client()->translateContentBlock('<div><p>Hello</p><p>World</p>', 'UI'), 'nor one leaving an element open');

        // A fragment ending inside a tag, a comment or an attribute value ends
        // that construct where it ends, and the id is the fragment's own.
        $parser = new HtmlParser();
        foreach ([
            '<p>Hello world</p><p>Second</p><script' => '<p>Hello world</p><p>Second</p><script></script>',
            '<p>Hello world</p><p>Second</p><!-- todo' => '<p>Hello world</p><p>Second</p>',
            '<p>Hello world</p><a title="Read more>Link' => '<p>Hello world</p><a title="Read more&gt;Link"></a>',
        ] as $html => $rendered) {
            $out = preg_replace('/ data-ls-contentblock="[0-9a-f]+"/', '', $this->client()->translateContentBlock($html, 'UI'));
            $this->assertSame($rendered, $out, $html);
        }
        $this->assertSame(['Hello world', 'Read more>Link'], array_values($parser->extractPhrases('<p>Hello world</p><a title="Read more>Link')));
        $this->assertSame('<p>Hello {name}</p><p>Second</p><style></style>', \Langsys\SDK\Html\ValueMarkers::placeholderHtml('<p>Hello <span data-ls-param="name">Bob</span></p><p>Second</p><style'));
    }

    /**
     * A body on a page in another charset comes back in the charset the page
     * is written out in, as the rest of the page does.
     */
    public function testABodyOnALatin1PageIsWrittenOutAsTheRestOfThePageIs(): void
    {
        $page = $this->client()->translatePage("<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\"></head><body><p>Hello</p><script>alert(\"Contrase\xF1a </b>\");</script></body></html>", 'UI');

        $this->assertStringContainsString("alert(\"Contrase\u{F1}a </b>\")", $page);
        $this->assertSame(1, preg_match('//u', $page), 'one encoding throughout');
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
