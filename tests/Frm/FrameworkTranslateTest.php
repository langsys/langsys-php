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
     * A line from the language files is the app's own, and comes back as the
     * framework prints it, markup and all.
     */
    public function testALanguageFileLineStaysRaw(): void
    {
        $client = $this->client()->useMissFallback(self::langFile());

        $this->assertSame(['html' => 'Lee <a href="/es/terminos">los términos</a>', 'from' => 'fallback'], $client->translateRich('Read our <a href="/terms">terms</a>'));
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

    public function testWithoutAnHtmlTagTheFirstElementIsTheRoot(): void
    {
        $this->assertSame('<!-- c --><div id="app" data-ls-resolved="es-es"><p>x</p></div>', $this->client()->markResolved('<!-- c --><div id="app"><p>x</p></div>'));
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
