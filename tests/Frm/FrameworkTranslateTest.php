<?php

namespace Langsys\SDK\Tests\Frm;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * The core's share of the framework-function family: resolution through the
 * catalog, then the framework's language files, then the source (FRM-3),
 * with who wrote each answer; rich lines rebuilt from the source's own
 * markup (FRM-8); and the resolved marker on a page as it is (FRM-4, GATE-10).
 */
class FrameworkTranslateTest extends TestCase
{
    /** @var MockHttpClient */
    private $http;

    private function client(array $catalog = ['__uncategorized__' => []], array $project = ['base_locale' => 'en-us', 'target_locales' => ['es-es']])
    {
        $this->http = new MockHttpClient();
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'write', 'write_enabled' => true] + $project]);
        $this->http->setResponse('GET', 'translations', ['data' => $catalog]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false]);
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

    private static function langFile()
    {
        return function ($phrase, $locale, $category, $argument) {
            $lines = ['es-es' => ['Welcome, {name}' => 'Bienvenido (archivo), {name}', 'Both' => 'Ambos (archivo)', 'Read our {m0o}terms{m0c}' => 'Lee <a href="/es/terminos">los términos</a>']];

            return isset($lines[$locale][$phrase]) ? $lines[$locale][$phrase] : null;
        };
    }

    // FRM-3

    /**
     * The spec's test: the catalog wins over the language files; a phrase
     * only in the files returns their translation, filled; one in neither
     * returns the source, filled.
     */
    public function testCatalogThenLanguageFilesThenSource(): void
    {
        $client = $this->client(['__uncategorized__' => ['Both' => 'Ambos (catálogo)']])->useMissFallback(self::langFile());

        $this->assertSame(['text' => 'Ambos (catálogo)', 'from' => 'catalog'], $client->resolve('Both'));
        $this->assertSame(['text' => 'Bienvenido (archivo), Ana', 'from' => 'fallback'], $client->resolve('Welcome, {name}', null, null, ['name' => 'Ana']));
        $this->assertSame(['text' => 'Neither Ana', 'from' => 'source'], $client->resolve('Neither {name}', null, null, ['name' => 'Ana']));
        $this->assertSame('Bienvenido (archivo), Ana', $client->translate('Welcome, {name}', null, '__uncategorized__', null, ['name' => 'Ana']), 'translate() is the same chain');
    }

    /**
     * A phrase registered but untranslated is a miss for the chain too, and
     * a catalog that cannot be read falls to the language files.
     */
    public function testAnUntranslatedOrUnreadableCatalogFallsToTheFiles(): void
    {
        $client = $this->client(['__uncategorized__' => ['Both' => null]])->useMissFallback(self::langFile());
        $this->assertSame(['text' => 'Ambos (archivo)', 'from' => 'fallback'], $client->resolve('Both'));

        $client = $this->client()->useMissFallback(self::langFile());
        $client->getTranslations('es-es');
        $this->http->setResponse('GET', 'translations', []);
        $reflection = new \ReflectionProperty(Client::class, 'translationsMemoryCache');
        $reflection->setAccessible(true);
        $reflection->setValue($client, []);
        $failing = new class extends MockHttpClient {
            public function get($endpoint, array $params = [])
            {
                throw new \Langsys\SDK\Exception\ApiException('down', 500);
            }
        };
        $translations = (new \ReflectionClass($client))->getProperty('translations');
        $translations->setAccessible(true);
        $inner = (new \ReflectionClass($translations->getValue($client)))->getProperty('http');
        $inner->setAccessible(true);
        $inner->setValue($translations->getValue($client), $failing);

        $this->assertSame(['text' => 'Ambos (archivo)', 'from' => 'fallback'], $client->resolve('Both'));
    }

    /**
     * The fallback is told the argument as written - a key for a key-style
     * call - and its answer is never registered.
     */
    public function testTheFallbackSeesTheArgumentAndRegistersNothing(): void
    {
        $seen = [];
        $client = $this->client()->useMissFallback(function ($phrase, $locale, $category, $argument) use (&$seen) {
            $seen[] = [$phrase, $locale, $category, $argument];

            return null;
        });

        $client->resolve('Save', null, 'UI');

        $this->assertSame([['Save', 'es-es', 'UI', 'Save']], $seen);
    }

    /**
     * For a key-style call the fallback gets the key as written, beside the
     * key's line as the phrase, so the framework can look the key up.
     */
    public function testTheFallbackGetsTheKeyOfAKeyStyleCall(): void
    {
        $dir = sys_get_temp_dir() . '/langsys-frm-' . bin2hex(random_bytes(4));
        mkdir($dir . '/en', 0777, true);
        file_put_contents($dir . '/en/checkout.php', '<?php return ["submit" => "Place order"];');

        try {
            $this->http = new MockHttpClient();
            $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us']]);
            $this->http->setResponse('GET', 'translations', ['data' => []]);
            $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false, 'migration' => ['files' => [$dir . '/en/checkout.php']]]);
            foreach (['http', 'translations', 'translatableItems'] as $property) {
                $prop = (new \ReflectionClass($client))->getProperty($property);
                $prop->setAccessible(true);
                if ($property === 'http') {
                    $prop->setValue($client, $this->http);
                    continue;
                }
                $inner = (new \ReflectionClass($prop->getValue($client)))->getProperty('http');
                $inner->setAccessible(true);
                $inner->setValue($prop->getValue($client), $this->http);
            }
            $client->setLocale('es-es');

            $seen = null;
            $client->useMissFallback(function ($phrase, $locale, $category, $argument) use (&$seen) {
                $seen = [$phrase, $category, $argument];

                return null;
            });
            $client->resolve('checkout.submit');

            $this->assertSame(['Place order', 'checkout', 'checkout.submit'], $seen);
        } finally {
            unlink($dir . '/en/checkout.php');
            rmdir($dir . '/en');
            rmdir($dir);
        }
    }

    /**
     * FRM-3: a catalog that cannot be read counts as empty - the chain
     * continues to the language files and the source, nothing reaches the
     * caller, and the cause is reported once per process, at debug.
     */
    public function testAnUnavailableCatalogIsReportedOnceAtDebug(): void
    {
        $noticed = new \ReflectionProperty(Client::class, 'catalogUnavailableNoticed');
        $noticed->setAccessible(true);
        $noticed->setValue(null, false);

        $logger = new \Langsys\SDK\Tests\Support\SpyLogger();
        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false, 'logger' => $logger, 'api_url' => 'http://127.0.0.1:9/api']);
        $client->setLocale('es-es');
        $client->useMissFallback(self::langFile());

        $this->assertSame(['text' => 'Bienvenido (archivo), Ana', 'from' => 'fallback'], $client->resolve('Welcome, {name}', null, null, ['name' => 'Ana']));
        $this->assertSame(['text' => 'Neither', 'from' => 'source'], $client->resolve('Neither'));
        $this->assertSame('Neither', $client->translate('Neither'));

        $lookup = array_filter($logger->entries, function ($entry) {
            return strpos($entry['message'], 'catalog') !== false || strpos($entry['message'], 'Translation lookup') !== false;
        });
        $this->assertSame(['debug'], array_values(array_unique(array_column($lookup, 'level'))));
        $this->assertCount(1, $lookup, 'once per process');
    }

    /**
     * Control: the quiet report is the framework function's. A fragment the
     * page renders still logs the failed lookup as an error.
     */
    public function testControlAFragmentLookupStillLogsAnError(): void
    {
        $logger = new \Langsys\SDK\Tests\Support\SpyLogger();
        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false, 'logger' => $logger, 'api_url' => 'http://127.0.0.1:9/api']);
        $client->setLocale('es-es');

        $client->translateContentBlock('<p>Fragment</p>');

        $this->assertContains('Translation lookup failed - returning source phrase', $logger->messagesAt('error'));
    }

    public function testAFailingFallbackReturnsTheSource(): void
    {
        $client = $this->client()->useMissFallback(function () {
            throw new \RuntimeException('broken');
        });

        $this->assertSame(['text' => 'Save', 'from' => 'source'], $client->resolve('Save'));
    }

    // FRM-8

    /**
     * The spec's test: catalog text never becomes markup - a tag a translator
     * added renders escaped - while the source's own link is rebuilt around
     * the translated run with its attributes as the source wrote them.
     */
    public function testARichLineRebuildsOnlyTheSourcesMarkup(): void
    {
        $client = $this->client(['__uncategorized__' => ['Read our {m0o}terms{m0c}' => 'Lee {m0o}los términos{m0c} <script>alert(1)</script>']]);

        $result = $client->translateRich('Read our <a href="/terms" class="x">terms</a>');

        $this->assertSame('catalog', $result['from']);
        $this->assertStringContainsString('<a href="/terms" class="x">los t', $result['html']);
        $this->assertStringContainsString('&lt;script&gt;', $result['html']);
        $this->assertStringNotContainsString('<script>', $result['html']);
    }

    /**
     * A token the source does not have is dropped, and an attribute a
     * translation tries to write is text, never an attribute.
     */
    public function testATranslationCannotAddATagOrChangeAnAttribute(): void
    {
        $client = $this->client(['__uncategorized__' => ['Read our {m0o}terms{m0c}' => 'Lee {m0o}los términos{m0c} {m1o}extra{m1c} <a href="evil">x</a>']]);

        $html = $client->translateRich('Read our <a href="/terms">terms</a>')['html'];

        $this->assertSame(1, substr_count($html, '<a '), 'only the source link');
        $this->assertStringNotContainsString('{m1', $html, 'the unknown token is dropped');
        $this->assertStringNotContainsString('<a href="evil"', $html);
        $this->assertStringContainsString('&lt;a href="evil"&gt;', $html, 'it is text');
    }

    /**
     * A rich line's fallback is told the line as the caller wrote it, markup
     * and all, beside the Langsys phrase, so a language file keyed by its
     * source text is found.
     */
    public function testARichLinesFallbackSeesTheLineAsWritten(): void
    {
        $seen = [];
        $client = $this->client()->useMissFallback(function ($phrase, $locale, $category, $argument) use (&$seen) {
            $seen[] = [$phrase, $argument];

            return $argument === 'Read our <a href="/terms">terms</a>' ? 'Lee <a href="/es/terminos">los términos</a>' : null;
        });

        $this->assertSame(['html' => 'Lee <a href="/es/terminos">los términos</a>', 'from' => 'fallback'], $client->translateRich('Read our <a href="/terms">terms</a>'));
        $this->assertSame([['Read our {m0o}terms{m0c}', 'Read our <a href="/terms">terms</a>']], $seen);
    }

    /**
     * A line from the language files is the app's own, and comes back as the
     * framework prints it, markup and all.
     */
    public function testALanguageFileLineStaysRaw(): void
    {
        $client = $this->client()->useMissFallback(self::langFile());

        $this->assertSame(['html' => 'Lee <a href="/es/terminos">los términos</a>', 'from' => 'fallback'], $client->translateRich('Read our <a href="/terms">terms</a>'));
    }

    /**
     * A marked value comes back in a language file's line as the marker it
     * was read from, its value escaped; inside a tag, where no marker can
     * stand, as its escaped value.
     */
    public function testALanguageFileLineGivesAMarkedValueBackAsItsMarker(): void
    {
        $lines = [
            'Hello {name} {m0o}x{m0c}' => 'Hola {name} <b>x</b>',
            'Hi {name} {m0o}y{m0c}' => 'Hola <abbr title="{name}">{name}</abbr> <i>y</i>',
        ];
        $client = $this->client()->useMissFallback(function ($phrase) use ($lines) {
            return isset($lines[$phrase]) ? $lines[$phrase] : null;
        });

        $this->assertSame(
            ['html' => 'Hola <!--ls:name-->B&amp;ob<!--/ls--> <b>x</b>', 'from' => 'fallback'],
            $client->translateRich('Hello <!--ls:name-->B&amp;ob<!--/ls--> <b>x</b>')
        );
        $this->assertSame(
            ['html' => 'Hola <abbr title="Ana &lt;3 &quot;A&quot;"><span data-ls-param="name">Ana &lt;3 "A"</span></abbr> <i>y</i>', 'from' => 'fallback'],
            $client->translateRich('Hi <span data-ls-param="name">Ana &lt;3 "A"</span> <i>y</i>')
        );
    }

    public function testWithNeitherTheSourceLineComesBack(): void
    {
        $client = $this->client();

        $result = $client->translateRich('Read our <a href="/terms">terms</a>, {name}', null, null, ['name' => '<b>Ana</b>']);

        $this->assertSame('source', $result['from']);
        $this->assertStringContainsString('Read our <a href="/terms">terms</a>, &lt;b&gt;Ana&lt;/b&gt;', $result['html'], 'a value is text');
    }

    // markResolved

    /**
     * The marker is added to the root's start tag and nothing else in the
     * page changes, byte for byte.
     */
    public function testAPageInANonBaseLocaleIsMarkedAsItIs(): void
    {
        $page = "<!DOCTYPE html>\n<html lang=\"es\">\n<head><title>T &amp; C</title></head><body><br><p>Olé &nbsp; <img src=x></p></body></html>";

        $this->assertSame(
            str_replace('<html lang="es">', '<html lang="es" data-ls-resolved="es-es">', $page),
            $this->client()->markResolved($page, 'es-ES')
        );
    }

    /**
     * A page without an <html>, a fragment response, has no root of its own:
     * each of its top-level elements is marked, as a browser builds them, and
     * nothing else changes.
     *
     * @dataProvider fragmentProvider
     */
    public function testWithoutAnHtmlTagEachTopLevelElementIsMarked(string $html, string $marked): void
    {
        $this->assertSame($marked, $this->client()->markResolved($html));
    }

    public function fragmentProvider(): array
    {
        $m = ' data-ls-resolved="es-es"';

        return [
            'one element' => ['<!-- c --><div id="app"><p>x</p></div>', '<!-- c --><div id="app"' . $m . '><p>x</p></div>'],
            'siblings' => ['<div>a</div><footer><p>b</p></footer>', '<div' . $m . '>a</div><footer' . $m . '><p>b</p></footer>'],
            'a void element among them' => ["<link rel=\"x\">\n<p>a<br>b</p><img src=\"i\"/>", "<link rel=\"x\"" . $m . ">\n<p" . $m . ">a<br>b</p><img src=\"i\"" . $m . "/>"],
            'paragraphs a browser closes' => ['<p>One<p>Two<div>Three</div>', '<p' . $m . '>One<p' . $m . '>Two<div' . $m . '>Three</div>'],
            'list items and cells stay inside' => ['<ul><li>a<li>b</ul><table><tr><td>1<td>2</table>', '<ul' . $m . '><li>a<li>b</ul><table' . $m . '><tr><td>1<td>2</table>'],
            'a script and an svg' => ['<script>x = "<p>";</script><svg><g><text>a</text></g></svg><p>b</p>', '<script' . $m . '>x = "<p>";</script><svg' . $m . '><g><text>a</text></g></svg><p' . $m . '>b</p>'],
            'an element marked already' => ['<p data-ls-resolved="false">a</p><p>b</p>', '<p data-ls-resolved="false">a</p><p' . $m . '>b</p>'],
            'an <html> mentioned in a comment' => ['<!-- <html> --><p>a</p><p>b</p>', '<!-- <html> --><p' . $m . '>a</p><p' . $m . '>b</p>'],
            'an unquoted value ending in a slash' => ['<a href=/docs/>Docs</a><img src=x/><br/><svg a/>', '<a href=/docs/' . $m . '>Docs</a><img src=x/' . $m . '><br' . $m . '/><svg a' . $m . '/>'],
            'a chart label in a foreignObject' => ["<svg viewBox=\"0 0 10 10\"><foreignObject><div>Label</div></foreignObject><rect width=\"1\"/></svg>\n<p>Next</p>", "<svg viewBox=\"0 0 10 10\"" . $m . "><foreignObject><div>Label</div></foreignObject><rect width=\"1\"/></svg>\n<p" . $m . ">Next</p>"],
            'a stray quote in a tag' => ['<img src="a.png"" alt="Logo"><p class="x">Hi</p>', '<img src="a.png"" alt="Logo"' . $m . '><p class="x"' . $m . '>Hi</p>'],
            'a start tag the input ends inside' => ['<p>a</p><p class="x', '<p' . $m . '>a</p><p class="x'],
            'an <html> after a comment that mentions one' => ['<!-- <html> --><html><body>x</body></html>', '<!-- <html> --><html' . $m . '><body>x</body></html>'],
        ];
    }

    /**
     * Unchanged: a base-locale page, an unknown base locale, and a root that
     * carries the marker in either spelling.
     *
     * @dataProvider unchangedProvider
     */
    public function testUnchanged($html, $locale, array $project): void
    {
        $this->assertSame($html, $this->client(['__uncategorized__' => []], $project)->markResolved($html, $locale));
    }

    public function unchangedProvider(): array
    {
        $project = ['base_locale' => 'en-us', 'target_locales' => ['es-es']];

        return [
            'the base locale' => ['<html><body>x</body></html>', 'en-us', $project],
            'an unknown base locale' => ['<html><body>x</body></html>', 'es-es', []],
            'already marked' => ['<html data-ls-resolved="fr-fr"><body>x</body></html>', 'es-es', $project],
            'already marked, long spelling' => ['<html data-langsys-resolved><body>x</body></html>', 'es-es', $project],
            'opted out' => ['<html data-ls-resolved="false"><body>x</body></html>', 'es-es', $project],
        ];
    }
}
