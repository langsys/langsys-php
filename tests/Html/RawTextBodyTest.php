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

        foreach (['<div><style-guide><p>Hello</p></style-guide><p>World</p><style>p{color:red}</style></div>', '<div><script-loader>Hello</script-loader><p>World</p><script>go("</b>")</script></div>'] as $html) {
            $this->assertSame($html, preg_replace('/ data-ls-contentblock="[0-9a-f]+"/', '', $this->client()->translateContentBlock($html, 'UI')), $html);
        }

        // A textarea's body is text: what it mentions is written as the
        // characters a browser shows.
        $this->assertSame(
            '<div><textarea>Type &lt;script&gt; here&lt;/script&gt;</textarea><p>World</p><script>go("</b>")</script></div>',
            preg_replace('/ data-ls-contentblock="[0-9a-f]+"/', '', $this->client()->translateContentBlock('<div><textarea>Type <script> here</script></textarea><p>World</p><script>go("</b>")</script></div>', 'UI'))
        );
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
     * WIRE-4: a selector category the matcher cannot read - a pseudo-class,
     * a combinator, an empty selector - is skipped with a warning, and the
     * page still renders under the rest; an unquoted attribute value, which
     * CSS allows, is read.
     */
    public function testASelectorTheMatcherCannotReadIsSkipped(): void
    {
        $page = $this->client(['Hello' => 'Hola'])->translatePage('<html><head><meta charset="utf-8"></head><body><form><input type="submit" value="Send"></form><ul><li>Hello</li></ul></body></html>', 'UI', ['li:first-child' => 'Lists', 'nav > *' => 'Nav', '' => 'x', 'input[type=submit]' => 'Forms']);

        $this->assertStringContainsString('<li>Hola</li>', $page);
    }

    /**
     * A page without an <html> is read as a browser reads it, inside one:
     * every top-level element is translated, and one that is a document - a
     * doctype, a head, a body or a title - is written back as one, its head
     * holding the head elements it led with, so its title and lang are set.
     * One that is a fragment is written back as the fragment it was.
     */
    public function testAPageWithoutAnHtmlElementIsTranslatedWhole(): void
    {
        $catalog = ['Hello world' => 'Hola mundo', 'Title' => 'Título'];
        foreach ([
            '<meta charset="utf-8"><title>Title</title><p>Hello world</p>' => '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"><title>Título</title></head><p>Hola mundo</p></html>' . "\n",
            '<head><meta charset="utf-8"><title>Title</title></head><body><p>Hello world</p></body>' => '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"><title>Título</title></head><body><p>Hola mundo</p></body></html>' . "\n",
            '<!DOCTYPE html><meta charset="utf-8"><title>Title</title><p>Hello world</p>' => '<!DOCTYPE html>' . "\n" . '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"><title>Título</title></head><p>Hola mundo</p></html>' . "\n",
            '<div><p>Hello world</p></div><footer><p>Hello world</p></footer>' => '<div data-ls-resolved="es-es"><p>Hola mundo</p></div><footer data-ls-resolved="es-es"><p>Hola mundo</p></footer>' . "\n",
            '<p>Hello world</p>' => '<p data-ls-resolved="es-es">Hola mundo</p>' . "\n",
            '<!DOCTYPE html><html lang="de"><meta charset="windows-1252"><title>Title</title><p>Hello world</p></html>' => '<!DOCTYPE html>' . "\n" . '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"><title>Título</title></head><p>Hola mundo</p></html>' . "\n",
            '<html><head><title>Title</title></head><p>Hello world</p></html>' => '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"><title>Título</title></head><p>Hola mundo</p></html>' . "\n",
        ] as $html => $expected) {
            $this->assertSame($expected, $this->client($catalog)->translatePage($html, 'UI'), $html);
        }
    }

    /**
     * A document is written out in UTF-8 and says so, in the head a browser
     * implies when it has none of its own, ahead of its body. A fragment is
     * given no head.
     */
    public function testADocumentWithoutAHeadIsGivenOneDeclaringUtf8(): void
    {
        $catalog = ['Hello world' => 'Hola mundo', 'Él está aquí' => 'Él está aquí'];
        foreach ([
            '<html><body><p>Hello world</p></body></html>' => '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"></head><body><p>Hola mundo</p></body></html>' . "\n",
            '<!DOCTYPE html><body><p>Hello world</p></body>' => '<!DOCTYPE html>' . "\n" . '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"></head><body><p>Hola mundo</p></body></html>' . "\n",
            "\n<!DOCTYPE html><p>Hello world</p>" => '<!DOCTYPE html>' . "\n" . '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"></head><p>Hola mundo</p></html>' . "\n",
            '<!DOCTYPE html><p>Él está aquí</p>' => '<!DOCTYPE html>' . "\n" . '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"></head><p>Él está aquí</p></html>' . "\n",
            '<html><!-- built 2026 --><body><p>Hello world</p></body></html>' => '<html lang="es-es" data-ls-resolved="es-es"><!-- built 2026 --><head><meta charset="utf-8"></head><body><p>Hola mundo</p></body></html>' . "\n",
            '<p>Hello world</p>' => '<p data-ls-resolved="es-es">Hola mundo</p>' . "\n",
        ] as $html => $expected) {
            $this->assertSame($expected, $this->client($catalog)->translatePage($html, 'UI'), $html);
        }
    }

    /**
     * Whether a page has an <html>, and whether it is a document, is read
     * from its tags as a browser reads them: a tag written in a script, a
     * comment or an attribute is text, and an svg's <title> is the drawing's,
     * not the page's.
     */
    public function testAPageIsReadFromItsTagsNotFromTextThatMentionsThem(): void
    {
        $catalog = ['Hello world' => 'Hola mundo', 'Title' => 'Título', 'Close' => 'Cerrar'];
        foreach ([
            '<button><svg viewBox="0 0 10 10"><title>Close dialog</title><path d="M0 0"/></svg>Close</button>' => '<button data-ls-contentblock="07e0ea6985fb5f6df2cbbd800a5fcd12" data-ls-resolved="es-es"><svg viewbox="0 0 10 10"><title>Close dialog</title><path d="M0 0"></path></svg>Close</button>' . "\n",
            '<p>Hello world</p><script>document.write("<html>");</script>' => '<p data-ls-resolved="es-es">Hola mundo</p><script data-ls-resolved="es-es">document.write("<html>");</script>' . "\n",
            '<div class="chart"><svg><foreignObject><span>Label</span></foreignObject><title>Chart</title></svg></div><p>Hello world</p>' => '<div class="chart" data-ls-contentblock="6a6e31c537d8574e5cbe643d2b90c194" data-ls-resolved="es-es"><svg><foreignobject><span>Label</span></foreignobject><title>Chart</title></svg></div><p data-ls-resolved="es-es">Hola mundo</p>' . "\n",
            '<p data-note="<html>">Hello world</p>' => '<p data-note="&lt;html&gt;" data-ls-resolved="es-es">Hola mundo</p>' . "\n",
            '<!DOCTYPE html><!-- from the <html> boilerplate --><title>Title</title><p>Hello world</p>' => '<!DOCTYPE html>' . "\n" . '<!-- from the <html> boilerplate --><html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"><title>Título</title></head><p>Hola mundo</p></html>' . "\n",
        ] as $html => $expected) {
            $this->assertSame($expected, $this->client($catalog)->translatePage($html, 'UI'), $html);
        }
    }

    /**
     * A `<` that starts no tag, comment or doctype is text, as a browser
     * reads it, on every path: "Price < 10" keeps all of its words. In a
     * textarea or a title every `<` is text; the bodies of xmp, iframe,
     * noembed and noframes, which a browser reads without decoding, come back
     * exactly as written.
     */
    public function testALessThanABrowserReadsAsTextKeepsWhatFollowsIt(): void
    {
        foreach ([
            '<p>Price < 10 and > 5</p>' => '<p>Price &lt; 10 and &gt; 5</p>',
            '<p>if a < b then</p><p>next</p>' => '<p>if a &lt; b then</p><p>next</p>',
            '<p>1 <2 is true, <3</p>' => '<p>1 &lt;2 is true, &lt;3</p>',
            '<p>a <</p>' => '<p>a &lt;</p>',
            '<textarea>a < b <!--> <p>c</p></textarea><p>After</p>' => '<textarea>a &lt; b &lt;!--&gt; &lt;p&gt;c&lt;/p&gt;</textarea><p>After</p>',
            '<xmp>a < b & <!--> <p>c</p> &amp;</xmp><p>After</p>' => '<xmp>a < b & <!--> <p>c</p> &amp;</xmp><p>After</p>',
            '<iframe>a < b & é</iframe><noembed><b>x</b> &lt;</noembed><noframes>1 <2</noframes><p>After</p>' => '<iframe>a < b & é</iframe><noembed><b>x</b> &lt;</noembed><noframes>1 <2</noframes><p>After</p>',
        ] as $html => $expected) {
            $page = $this->client()->translatePage('<html><head></head><body>' . $html . '</body></html>', 'UI');
            $this->assertStringContainsString('<body>' . $expected . '</body>', $page, 'page: ' . $html);
            $block = $this->client()->translateContentBlock($html, 'UI');
            $this->assertSame($expected, preg_replace('/ data-ls-[a-z]+="[^"]*"/', '', $block), 'block: ' . $html);
        }

        $this->assertSame('<p data-ls-resolved="es-es">x</p><xmp data-ls-resolved="es-es">a < b & c</xmp>' . "\n", $this->client()->translatePage('<p>x</p><xmp>a < b & c', 'UI'), 'a fragment whose raw body the input ends inside');

        $page = $this->client()->translatePage('<html><head><title>Q < A</title></head><body><p>x</p></body></html>', 'UI');
        $this->assertStringContainsString('<title>Q &lt; A</title>', $page, 'a title');
    }

    /**
     * A body written back as raw text can never be closed by what a
     * translation put in it: one that would end the element stays escaped.
     */
    public function testATranslationCannotCloseARawTextBody(): void
    {
        $catalog = ['Your browser cannot show frames' => '</iframe><script>alert(1)</script>', 'Old text' => '</XMP><img src=x onerror=alert(1)>'];
        $html = '<html><head></head><body><iframe>Your browser cannot show frames</iframe><xmp>Old text</xmp><p>After</p></body></html>';
        $page = $this->client($catalog)->translatePage($html, 'UI');

        $this->assertStringNotContainsString('<script>alert(1)', $page);
        $this->assertStringNotContainsString('<img src=x', $page);
        $this->assertStringContainsString('<p>After</p>', $page);
    }

    /**
     * `<!-->` is a whole comment, as a browser reads it, on every path: what
     * follows it is the page's, not more of the comment.
     */
    public function testAnEmptyCommentKeepsWhatFollowsIt(): void
    {
        $catalog = ['Hello world' => 'Hola mundo'];
        foreach ([
            '<!--><p>Hello world</p>' => '<!----><p data-ls-resolved="es-es">Hola mundo</p>' . "\n",
            '<html><body><!--><p>Hello world</p><!---><p>Hello world</p></body></html>' => '<html lang="es-es" data-ls-resolved="es-es"><head><meta charset="utf-8"></head><body><!----><p>Hola mundo</p><!----><p>Hola mundo</p></body></html>' . "\n",
        ] as $html => $expected) {
            $this->assertSame($expected, $this->client($catalog)->translatePage($html, 'UI'), $html);
        }

        $this->assertSame('<div><!----><p>Hola mundo</p></div>', $this->client($catalog)->translateContentBlock('<div><!--><p>Hello world</p></div>', 'UI'));
        $this->assertSame('<div><!----><p>Hola mundo</p></div>', $this->client($catalog)->translateContentBlock('<div><!---><p>Hello world</p></div>', 'UI'));
        $this->assertSame('<script>a = "<!-->";</script><p>Hola mundo</p>', $this->client($catalog)->translateContentBlock('<script>a = "<!-->";</script><p>Hello world</p>', 'UI'), 'a script keeps its text as written');
    }

    /**
     * A page led by a long comment, a build banner or an inlined licence, is
     * read past it, whole.
     */
    public function testAPageLedByALongCommentIsTranslatedWhole(): void
    {
        $comment = '<!--' . str_repeat(' licence text', 100000) . ' -->';
        $page = $this->client(['Hello world' => 'Hola mundo'])->translatePage($comment . '<p>Hello world</p><p>Hello world</p>', 'UI');

        $this->assertSame($comment . '<p data-ls-resolved="es-es">Hola mundo</p><p data-ls-resolved="es-es">Hola mundo</p>' . "\n", $page);
    }

    /**
     * WIRE-4: a page with no element - only whitespace, a comment or a
     * doctype - comes back as given, never an exception.
     */
    public function testAPageWithNoElementComesBackAsGiven(): void
    {
        foreach (["  \n", '<!-- x -->', '<!DOCTYPE html>'] as $html) {
            $this->assertSame($html, $this->client()->translatePage($html, 'UI'), json_encode($html));
        }
    }

    /**
     * A page that opens with a byte-order mark is read as the UTF-8 it
     * marks, its <html> and <head> intact.
     */
    public function testAPageOpeningWithAByteOrderMarkKeepsItsStructure(): void
    {
        $page = $this->client(['Hello' => 'Hola'])->translatePage("\xEF\xBB\xBF<html><head><title>T</title></head><body><p>Hello</p><p>Café</p></body></html>", 'UI');

        $this->assertStringContainsString('<html lang="es-es"', $page);
        $this->assertStringContainsString('<head><meta charset="utf-8">', $page);
        $this->assertStringContainsString('<p>Hola</p><p>Café</p>', $page);
        $this->assertStringNotContainsString("\u{FEFF}</p>", $page);
    }

    /**
     * The page goes out in UTF-8, and the first charset a browser's prescan
     * finds in it says so, even when a meta is written in its title's text.
     */
    public function testThePrescanOfTheOutputFindsUtf8First(): void
    {
        $title = '<title>Never write <meta charset="windows-1252"> again</title>';
        $cafe = "<p>Caf\xE9</p>";
        foreach ([
            '<html><head>' . $title . '<meta charset="windows-1252"></head><body>' . $cafe . '</body></html>',
            '<html><head>' . $title . '</head><body>' . $cafe . '</body></html>',
            '<html><head><noscript><meta charset="windows-1252"></noscript></head><body>' . $cafe . '</body></html>',
            "<html><head><meta charset=\"windows-1252\"><title>Caf\xE9</title></head><body>" . $cafe . '</body></html>',
        ] as $html) {
            $page = $this->client()->translatePage($html, 'UI');

            $this->assertStringContainsString('<p>Café</p>', \Langsys\SDK\Html\Charset::toUtf8($page), $html);
            $this->assertSame(1, preg_match('/<head><meta charset="utf-8">|<meta charset="utf-8"><\/head>/', $page), $html);
        }
        $this->assertStringContainsString('<title>Never write &lt;meta charset="windows-1252"&gt; again</title>', $this->client()->translatePage('<html><head>' . $title . '</head><body></body></html>', 'UI'), 'the title is its text');
    }

    /**
     * A meta written where it declares nothing - the text of a textarea or a
     * title, an inert template, an SVG - is left as written.
     */
    public function testAMetaThatDeclaresNothingIsLeftAsWritten(): void
    {
        $page = $this->client()->translatePage('<html><head><title>Never write <meta charset="windows-1252"> again</title></head><body></body></html>', 'UI');
        $this->assertStringContainsString('windows-1252', $page, 'a title');

        foreach (['<plaintext><meta charset="windows-1252">', '<iframe><meta charset="windows-1252"></iframe>', '<noembed><meta charset="windows-1252"></noembed>', '<noframes><meta charset="windows-1252"></noframes>', '<textarea><meta charset="windows-1252"></textarea>', '<xmp><meta charset="windows-1252"></xmp>', '<template><meta charset="windows-1252"></template>', '<noscript><meta charset="windows-1252"></noscript>', '<svg><meta charset="windows-1252"></meta></svg>', '<math><meta charset="windows-1252"></meta></math>'] as $body) {
            $page = $this->client()->translatePage('<html><head><meta charset="utf-8"></head><body>' . $body . '</body></html>', 'UI');

            $this->assertStringContainsString('windows-1252', $page, $body);
        }
    }

    /**
     * A page is decoded as a browser decodes it before anything reads it: a
     * byte-order mark first, then the charset its first 1024 bytes declare -
     * Latin-1 and ASCII labels read as windows-1252 - else UTF-8 when its
     * bytes are UTF-8 and windows-1252 when they are not; a byte invalid in
     * UTF-8 becomes U+FFFD, and the rest of the page is still UTF-8.
     */
    public function testAPageIsDecodedAsABrowserDecodesIt(): void
    {
        $quotes = "<p>He said \x93hi\x94 \x96 \x80 5</p>";
        foreach ([
            'iso-8859-1' => ['<html><head><meta charset="iso-8859-1"></head><body>' . $quotes . '</body></html>', '<p>He said “hi” – € 5</p>'],
            'latin1' => ['<html><head><meta http-equiv="Content-Type" content="text/html; charset=latin1"></head><body>' . $quotes . '</body></html>', '<p>He said “hi” – € 5</p>'],
            'undeclared, not UTF-8' => ['<html><head></head><body>' . $quotes . '</body></html>', '<p>He said “hi” – € 5</p>'],
            'byte-order mark over a meta' => ["\xEF\xBB\xBF<html><head><meta charset=\"windows-1252\"></head><body><p>M\xC3\xBCller</p></body></html>", '<p>Müller</p>'],
            'us-ascii, whole page kept' => ["<html><head><meta charset=\"us-ascii\"></head><body><p>caf\xC3\xA9</p><p>after</p></body></html>", '<p>after</p>'],
            'one bad byte' => ["<html><head><meta charset=\"utf-8\"></head><body><p>caf\xE9</p><p>M\xC3\xBCller</p></body></html>", "<p>caf\u{FFFD}</p><p>Müller</p>"],
            'a meta declaring UTF-16, read as UTF-8' => ["<html><head><meta charset=\"utf-16\"></head><body><p>M\xC3\xBCller</p></body></html>", '<p>Müller</p>'],
            'shift_jis' => ["<html><head><meta charset=\"shift_jis\"></head><body><p>\x93\xfa\x96\x7b</p></body></html>", '<p>日本</p>'],
            'utf-8, the control' => ['<html><head><meta charset="utf-8"></head><body><p>Müller</p></body></html>', '<p>Müller</p>'],
        ] as $label => list($html, $expected)) {
            $page = $this->client()->translatePage($html, 'UI');

            $this->assertStringContainsString($expected, $page, $label);
            $this->assertSame(1, preg_match('//u', $page), 'valid UTF-8: ' . $label);
        }

        $utf16 = "\xFF\xFE" . \UConverter::transcode('<html><head></head><body><p>Müller</p></body></html>', 'UTF-16LE', 'UTF-8');
        $this->assertStringContainsString('<p>Müller</p>', $this->client()->translatePage($utf16, 'UI'), 'UTF-16, by its byte-order mark');

        $block = $this->client()->translateContentBlock("<p>caf\xE9</p><p>M\xC3\xBCller</p>", 'UI');
        $this->assertStringContainsString("<p>caf\u{FFFD}</p><p>Müller</p>", $block, 'a fragment is read as UTF-8, a bad byte as U+FFFD');
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
     * A fragment's own stray `</div>` closes nothing of the SDK's: what
     * follows it is still the fragment's, rendered and registered, as an
     * element's innerHTML keeps it.
     */
    public function testAStrayEndTagInAFragmentKeepsWhatFollowsIt(): void
    {
        $client = $this->client();
        $this->assertSame('<p>Hello world</p><p>Tail</p>', preg_replace('/ data-ls-contentblock="[0-9a-f]+"/', '', $client->translateContentBlock('<p>Hello world</p></div><p>Tail</p>', 'UI')));
        $this->assertSame([['Hello world', 'Tail']], array_values(array_map(function ($b) {
            return $b['phrases'];
        }, $client->getPendingContentBlocks())));

        $this->assertSame('Hello world Tail', $this->client()->translateRich('Hello world</div> Tail', null, 'UI')['html']);
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
